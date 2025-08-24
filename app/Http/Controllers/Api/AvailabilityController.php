<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Menu;
use App\Models\Reservation;
use App\Models\Shift;
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
        ]);

        $store = Store::findOrFail($validated['store_id']);
        $menu = Menu::findOrFail($validated['menu_id']);
        $date = Carbon::parse($validated['date']);
        
        // その日に予約可能なシフトがあるかチェック
        $availableShifts = Shift::where('store_id', $store->id)
            ->whereDate('shift_date', $date)
            ->where('is_available_for_reservation', true)
            ->whereIn('status', ['scheduled', 'working'])
            ->get();
        
        if ($availableShifts->isEmpty()) {
            return response()->json([
                'message' => 'この日は予約可能なスタッフがいません',
                'available_slots' => []
            ]);
        }
        
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
        
        // 予約可能時間スロットを生成（30分単位）
        $slots = [];
        $menuDuration = $menu->duration ?? 60; // メニューの所要時間（分）
        
        // 各シフトの勤務時間内でスロットを生成
        foreach ($availableShifts as $shift) {
            $shiftStart = Carbon::parse($date->format('Y-m-d') . ' ' . $shift->start_time);
            $shiftEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $shift->end_time);
            
            // シフトの開始時間と営業開始時間の遅い方を使用
            $effectiveStart = $shiftStart->gt($openTime) ? $shiftStart : $openTime;
            // シフトの終了時間と営業終了時間の早い方を使用
            $effectiveEnd = $shiftEnd->lt($closeTime) ? $shiftEnd : $closeTime;
            
            $currentTime = $effectiveStart->copy();
            
            while ($currentTime->copy()->addMinutes($menuDuration)->lte($effectiveEnd)) {
                // 休憩時間中はスキップ
                if ($shift->break_start && $shift->break_end) {
                    $breakStart = Carbon::parse($date->format('Y-m-d') . ' ' . $shift->break_start);
                    $breakEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $shift->break_end);
                    
                    if ($currentTime->gte($breakStart) && $currentTime->lt($breakEnd)) {
                        $currentTime = $breakEnd->copy();
                        continue;
                    }
                }
                
                $slotKey = $currentTime->format('H:i');
                if (!isset($slots[$slotKey])) {
                    $slots[$slotKey] = [
                        'time' => $currentTime->format('H:i'),
                        'datetime' => $currentTime->format('Y-m-d H:i:s'),
                        'staff_count' => 1
                    ];
                } else {
                    $slots[$slotKey]['staff_count']++;
                }
                
                $currentTime->addMinutes(30);
            }
        }
        
        // 配列を値のみに変換してソート
        $slots = array_values($slots);
        usort($slots, function ($a, $b) {
            return strcmp($a['time'], $b['time']);
        });
        
        // 既存の予約を取得
        $existingReservations = Reservation::where('store_id', $store->id)
            ->whereDate('reservation_date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->get();
        
        // 予約済みの時間帯を除外
        $availableSlots = collect($slots)->filter(function ($slot) use ($existingReservations, $menuDuration) {
            $slotStart = Carbon::parse($slot['datetime']);
            $slotEnd = $slotStart->copy()->addMinutes($menuDuration);
            
            foreach ($existingReservations as $reservation) {
                $resStart = Carbon::parse($reservation->reservation_date . ' ' . $reservation->start_time);
                $resEnd = Carbon::parse($reservation->reservation_date . ' ' . $reservation->end_time);
                
                // 時間帯が重複する場合は除外
                if (
                    ($slotStart->gte($resStart) && $slotStart->lt($resEnd)) ||
                    ($slotEnd->gt($resStart) && $slotEnd->lte($resEnd)) ||
                    ($slotStart->lte($resStart) && $slotEnd->gte($resEnd))
                ) {
                    return false;
                }
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
     * 指定月の予約可能日を取得
     */
    public function getAvailableDays(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'year' => 'required|integer|min:2024|max:2030',
            'month' => 'required|integer|min:1|max:12',
        ]);
        
        $store = Store::findOrFail($validated['store_id']);
        $startDate = Carbon::create($validated['year'], $validated['month'], 1);
        $endDate = $startDate->copy()->endOfMonth();
        
        $availableDays = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate->lte($endDate)) {
            $dayOfWeek = strtolower($currentDate->format('l'));
            $businessHours = collect($store->business_hours)->firstWhere('day', $dayOfWeek);
            
            $availableDays[] = [
                'date' => $currentDate->format('Y-m-d'),
                'day_of_week' => $dayOfWeek,
                'is_closed' => !$businessHours || $businessHours['is_closed'],
                'is_past' => $currentDate->lt(today()),
            ];
            
            $currentDate->addDay();
        }
        
        return response()->json([
            'message' => '予約可能日を取得しました',
            'year' => $validated['year'],
            'month' => $validated['month'],
            'available_days' => $availableDays
        ]);
    }
}