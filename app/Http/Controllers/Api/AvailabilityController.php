<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Menu;
use App\Models\Reservation;
use App\Models\Shift;
use App\Models\BlockedTimePeriod;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AvailabilityController extends Controller
{
    /**
     * 指定された日付の予約可能時間を取得
     */
    public function getAvailableSlots(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'menu_id' => 'required|exists:menus,id',
            'date' => 'required|date|after_or_equal:today',
            'staff_id' => 'nullable|exists:users,id', // 指名予約用
            'customer_id' => 'nullable|exists:customers,id', // カルテチェック用
        ]);

        $store = Store::findOrFail($validated['store_id']);
        $menu = Menu::findOrFail($validated['menu_id']);
        $date = Carbon::parse($validated['date']);
        $staffId = $validated['staff_id'] ?? null;
        $customerId = $validated['customer_id'] ?? null;
        
        // 指名予約の制限チェック
        if ($staffId && !$this->canMakeStaffReservation($store, $customerId)) {
            return response()->json([
                'message' => 'この店舗では指名予約はできません',
                'available_slots' => []
            ]);
        }
        
        // シフトチェックを削除 - 営業時間のみで判断
        
        // 営業時間を取得
        $dayOfWeek = strtolower($date->format('l')); // monday, tuesday, etc.
        $businessHours = collect($store->business_hours)->firstWhere('day', $dayOfWeek);
        
        // 定休日チェック
        if (!$businessHours || $businessHours['is_closed']) {
            return response()->json([
                'message' => 'この日は定休日です',
                'available_slots' => []
            ]);
        }
        
        // 開店・閉店時間を取得
        $openTime = Carbon::parse($date->format('Y-m-d') . ' ' . $businessHours['open_time']);
        $closeTime = Carbon::parse($date->format('Y-m-d') . ' ' . $businessHours['close_time']);
        
        // 予約可能時間スロットを生成（店舗設定の間隔で）
        $slots = [];
        $menuDuration = $menu->duration ?? 60; // メニューの所要時間（分）
        $slotDuration = $store->reservation_slot_duration ?? 30; // 店舗ごとの予約間隔（分）
        
        // 営業時間内でスロットを生成（シフト不要）
        $currentTime = $openTime->copy();
        
        // 予約開始時刻が営業終了時刻以前であればOK（メニューが営業時間を超えても可）
        while ($currentTime->lte($closeTime)) {
            $slotKey = $currentTime->format('H:i');
            $slots[$slotKey] = [
                'time' => $currentTime->format('H:i'),
                'datetime' => $currentTime->format('Y-m-d H:i:s'),
                'available' => true
            ];
            
            $currentTime->addMinutes($slotDuration);
        }
        
        // 配列を値のみに変換してソート
        $slots = array_values($slots);
        usort($slots, function ($a, $b) {
            return strcmp($a['time'], $b['time']);
        });
        
        // 既存の予約を取得（メインラインのみカウント）
        $existingReservations = Reservation::where('store_id', $store->id)
            ->whereDate('reservation_date', $date)
            ->where('line_type', 'main')  // メインラインのみ
            ->whereNotIn('status', ['cancelled', 'canceled', 'no_show'])
            ->get();
        
        // ブロックされた時間帯を取得
        $blockedPeriods = BlockedTimePeriod::where('store_id', $store->id)
            ->whereDate('blocked_date', $date)
            ->get();

        // 予約済みの時間帯とブロック時間を除外
        $availableSlots = collect($slots)->filter(function ($slot) use ($existingReservations, $blockedPeriods, $menuDuration, $date, $store) {
            $slotStart = Carbon::parse($slot['datetime']);
            $slotEnd = $slotStart->copy()->addMinutes($menuDuration);

            // ブロックされた時間帯との重複チェック
            // 1. 全体ブロック（line_typeがnull）がある場合は予約不可
            $hasGlobalBlock = false;
            foreach ($blockedPeriods as $blocked) {
                if ($blocked->line_type === null) {
                    $blockStart = Carbon::parse($date->format('Y-m-d') . ' ' . $blocked->start_time);
                    $blockEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $blocked->end_time);

                    if (
                        ($slotStart->gte($blockStart) && $slotStart->lt($blockEnd)) ||
                        ($slotEnd->gt($blockStart) && $slotEnd->lte($blockEnd)) ||
                        ($slotStart->lte($blockStart) && $slotEnd->gte($blockEnd))
                    ) {
                        $hasGlobalBlock = true;
                        break;
                    }
                }
            }

            if ($hasGlobalBlock) {
                return false;
            }

            // 2. ライン別ブロックをカウント（メインラインのみ）
            $mainLinesCount = $store->main_lines_count ?? 1;
            $blockedMainLinesCount = 0;

            foreach ($blockedPeriods as $blocked) {
                if ($blocked->line_type === 'main') {
                    $blockStart = Carbon::parse($date->format('Y-m-d') . ' ' . $blocked->start_time);
                    $blockEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $blocked->end_time);

                    if (
                        ($slotStart->gte($blockStart) && $slotStart->lt($blockEnd)) ||
                        ($slotEnd->gt($blockStart) && $slotEnd->lte($blockEnd)) ||
                        ($slotStart->lte($blockStart) && $slotEnd->gte($blockEnd))
                    ) {
                        $blockedMainLinesCount++;
                    }
                }
            }

            // 全てのメインラインがブロックされている場合は予約不可
            if ($blockedMainLinesCount >= $mainLinesCount) {
                return false;
            }
            
            // 既存予約との重複チェック（店舗設定に応じた席数制御）
            $overlappingCount = 0;
            foreach ($existingReservations as $reservation) {
                // reservation_dateから日付部分のみを抽出
                $resDate = Carbon::parse($reservation->reservation_date)->format('Y-m-d');
                $resStart = Carbon::parse($resDate . ' ' . $reservation->start_time);
                $resEnd = Carbon::parse($resDate . ' ' . $reservation->end_time);
                
                if (
                    ($slotStart->gte($resStart) && $slotStart->lt($resEnd)) ||
                    ($slotEnd->gt($resStart) && $slotEnd->lte($resEnd)) ||
                    ($slotStart->lte($resStart) && $slotEnd->gte($resEnd))
                ) {
                    $overlappingCount++;
                }
            }
            
            // 店舗設定に応じてキャパシティを決定
            $storeCapacity = $this->getSlotCapacity($store, $slotStart, $date);
            if ($overlappingCount >= $storeCapacity) {
                return false;
            }
            
            // 現在時刻より過去の時間は除外
            if ($slotStart->lte(now())) {
                return false;
            }
            
            return true;
        })->values();
        
        return response()->json([
            'message' => '予約可能時間を取得しました',
            'date' => $date->format('Y-m-d'),
            'business_hours' => [
                'open' => $businessHours['open_time'],
                'close' => $businessHours['close_time']
            ],
            'available_slots' => $availableSlots->map(function ($slot) {
                return [
                    'time' => $slot['time'],
                    'available' => true
                ];
            })
        ]);
    }
    
    /**
     * 店舗設定に応じた時間帯別キャパシティを取得
     */
    private function getSlotCapacity(Store $store, Carbon $slotTime, Carbon $date): int
    {
        // シフトベースの場合
        if ($store->use_staff_assignment) {
            $shifts = Shift::where('store_id', $store->id)
                ->whereDate('shift_date', $date)
                ->where('is_available_for_reservation', true)
                ->where('status', 'scheduled')
                ->get();
            
            $timeStr = $slotTime->format('H:i:s');
            
            // この時間帯に勤務中のスタッフ数を計算（休憩時間は考慮しない）
            $availableStaffCount = $shifts->filter(function ($shift) use ($timeStr) {
                // 勤務時間内かチェック（休憩時間でも予約OK）
                return $timeStr >= $shift->start_time && $timeStr <= $shift->end_time;
            })->count();
            
            // min(設備台数, スタッフ数) で最終的な席数を決定
            $equipmentCapacity = $store->shift_based_capacity ?? 1;
            return min($equipmentCapacity, max($availableStaffCount, 0));
        }
        
        // 営業時間ベース（メインラインのみを公開）
        return $store->main_lines_count ?? 1;  // サブラインは内部管理用のため含めない
    }
    
    /**
     * 指名予約が可能かチェック
     */
    private function canMakeStaffReservation(Store $store, ?int $customerId): bool
    {
        // 営業時間ベースの店舗では指名予約不可
        if (!$store->use_staff_assignment) {
            return false;
        }
        
        // 顧客IDがない場合は指名不可（初回予約）
        if (!$customerId) {
            return false;
        }
        
        // 過去に予約履歴がある顧客のみ指名可能
        $hasReservationHistory = Reservation::where('customer_id', $customerId)
            ->where('store_id', $store->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->exists();
        
        return $hasReservationHistory;
    }
    
    /**
     * 指定月の予約可能日を取得
     */
    public function getAvailableDays(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'menu_id' => 'nullable|exists:menus,id',  // menu_idはオプション
            'year' => 'nullable|integer|min:2024|max:2030',
            'month' => 'nullable|integer|min:1|max:12',
        ]);
        
        $store = Store::findOrFail($validated['store_id']);
        
        // yearとmonthが指定されていない場合は今月から7日間を返す
        if (!isset($validated['year']) || !isset($validated['month'])) {
            $startDate = Carbon::today();
            $endDate = $startDate->copy()->addDays(7);
        } else {
            $startDate = Carbon::create($validated['year'], $validated['month'], 1);
            $endDate = $startDate->copy()->endOfMonth();
        }
        
        $menu = isset($validated['menu_id']) ? Menu::find($validated['menu_id']) : null;
        $availability = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate->lte($endDate)) {
            if ($currentDate->gte(today())) {  // 過去の日付はスキップ
                $dayOfWeek = strtolower($currentDate->format('l'));
                $businessHours = collect($store->business_hours)->firstWhere('day', $dayOfWeek);
                
                // 営業日かつ定休日でない場合は空き時間を取得
                if ($businessHours && !$businessHours['is_closed']) {
                    $openTime = Carbon::parse($currentDate->format('Y-m-d') . ' ' . $businessHours['open_time']);
                    $closeTime = Carbon::parse($currentDate->format('Y-m-d') . ' ' . $businessHours['close_time']);
                    
                    $slots = [];
                    $currentTime = $openTime->copy();
                    $slotDuration = $store->reservation_slot_duration ?? 30;
                    
                    while ($currentTime->lte($closeTime)) {
                        // 現在時刻より未来の時間のみ追加
                        if ($currentTime->gt(now())) {
                            $slots[] = $currentTime->format('H:i');
                        }
                        $currentTime->addMinutes($slotDuration);
                    }
                    
                    if (count($slots) > 0) {
                        $availability[$currentDate->format('Y-m-d')] = $slots;
                    }
                }
            }
            
            $currentDate->addDay();
        }
        
        return response()->json([
            'message' => '予約可能日を取得しました',
            'availability' => $availability
        ]);
    }
}