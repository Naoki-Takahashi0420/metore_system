<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Store;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Shift;
use App\Models\BlockedTimePeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class ReservationRescheduleController extends Controller
{
    public function show(Reservation $reservation)
    {
        // 権限チェック
        $user = auth()->user();
        if (!$user) {
            abort(401);
        }

        // 予約が存在し、編集可能な状態かチェック
        if ($reservation->status !== 'booked') {
            return redirect('/admin/reservations')->with('error', '編集可能な予約ではありません');
        }

        // ユーザーの権限に応じて店舗を制限
        if ($user->hasRole('super_admin')) {
            $stores = Store::where('is_active', true)->get();
        } elseif ($user->hasRole('owner')) {
            $stores = $user->manageableStores()->where('is_active', true)->get();
        } elseif ($user->hasRole('staff')) {
            $stores = collect([$user->store])->filter();
        } else {
            abort(403);
        }

        // アクセス権限チェック
        if (!$stores->contains('id', $reservation->store_id)) {
            abort(403, 'この予約にアクセスする権限がありません');
        }

        $selectedStore = $reservation->store;
        $menuCategories = MenuCategory::where('store_id', $selectedStore->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['menus' => function($query) {
                $query->where('is_active', true)->orderBy('sort_order');
            }])
            ->get();

        // 選択された週を取得（デフォルトは今週）
        $weekOffset = (int) request()->get('week', 0);

        // 店舗の最大予約可能日数を取得（デフォルト30日）
        $maxAdvanceDays = $selectedStore->max_advance_days ?? 30;

        // 最大週数を計算
        $maxWeeks = ceil($maxAdvanceDays / 7);

        // 週オフセットが最大値を超えないように制限
        if ($weekOffset >= $maxWeeks) {
            $weekOffset = $maxWeeks - 1;
        }

        // 今日から始まる7日間を表示
        $startDate = Carbon::today()->addWeeks($weekOffset);

        $dates = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dates[] = [
                'date' => $date,
                'formatted' => $date->format('n/j'),
                'day' => $this->getDayInJapanese($date->dayOfWeek),
                'is_today' => $date->isToday(),
                'is_past' => $date->lt(Carbon::today()) // 今日より前の日付のみtrueにする
            ];
        }

        // 営業時間から時間スロットを生成
        $timeSlots = $this->generateTimeSlots($selectedStore);

        // 空き状況を取得
        $availability = $this->getAvailability(
            $selectedStore,
            $reservation->menu,
            $dates,
            $timeSlots,
            $reservation->staff_id,
            $reservation->id // 現在の予約IDを除外
        );

        return view('admin.reservations.reschedule', compact(
            'reservation',
            'selectedStore',
            'stores',
            'menuCategories',
            'dates',
            'timeSlots',
            'availability',
            'weekOffset',
            'maxWeeks'
        ));
    }

    public function update(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'reservation_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required',
            'staff_id' => 'nullable|exists:users,id',
        ], [
            'reservation_date.required' => '予約日を選択してください',
            'reservation_date.date' => '正しい日付を入力してください',
            'reservation_date.after_or_equal' => '過去の日付は選択できません',
            'start_time.required' => '開始時間を選択してください',
        ]);

        // 権限チェック
        $user = auth()->user();
        if (!$user) {
            abort(401);
        }

        // 日程変更では店舗とメニューは変更できない
        $store = $reservation->store;
        $menu = $reservation->menu;

        // アクセス権限チェック
        if ($user->hasRole('staff') && $user->store_id !== $store->id) {
            abort(403, 'この店舗の予約を変更する権限がありません');
        } elseif ($user->hasRole('owner')) {
            $manageableStoreIds = $user->manageableStores()->pluck('stores.id');
            if (!$manageableStoreIds->contains($store->id)) {
                abort(403, 'この店舗の予約を変更する権限がありません');
            }
        }

        DB::beginTransaction();
        try {
            // 終了時間を計算
            $startTime = Carbon::createFromTimeString($validated['start_time']);
            $endTime = $startTime->copy()->addMinutes($menu->duration);

            // 空き状況チェック
            $availability = $this->checkSlotAvailability(
                $store,
                $validated['reservation_date'],
                $validated['start_time'],
                $endTime->format('H:i'),
                $validated['staff_id'],
                $reservation->id
            );

            if (!$availability['available']) {
                return back()->withErrors(['error' => $availability['message']]);
            }

            // 予約を更新（店舗とメニューは変更しない）
            $reservation->update([
                'reservation_date' => $validated['reservation_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $endTime->format('H:i:s'),
                'staff_id' => $validated['staff_id'],
                'updated_at' => now(),
            ]);

            DB::commit();

            // 変更後の予約情報を含めた詳細なメッセージ
            $customer = $reservation->customer;
            $newDate = Carbon::parse($validated['reservation_date'])->format('Y年n月j日');
            $newTime = Carbon::parse($validated['start_time'])->format('H:i');

            $message = "予約日程を変更しました\n";
            $message .= "【顧客名】{$customer->last_name} {$customer->first_name} 様\n";
            $message .= "【新日時】{$newDate} {$newTime}〜\n";
            $message .= "【メニュー】{$menu->name}";

            return redirect('/admin')
                ->with('success', $message)
                ->with('reservation_updated', true)
                ->with('reservation_id', $reservation->id);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => '予約の変更中にエラーが発生しました: ' . $e->getMessage()]);
        }
    }

    private function generateTimeSlots($store)
    {
        $slots = [];

        // 店舗の営業時間を取得（デフォルト値も設定）
        $openTime = '09:00';
        $closeTime = '21:00';

        if ($store && $store->business_hours) {
            $businessHours = is_string($store->business_hours)
                ? json_decode($store->business_hours, true)
                : $store->business_hours;

            if (is_array($businessHours) && !empty($businessHours)) {
                // 営業時間の最小開始時間と最大終了時間を取得
                $earliestOpen = null;
                $latestClose = null;

                foreach ($businessHours as $dayHours) {
                    if (!isset($dayHours['is_closed']) || !$dayHours['is_closed']) {
                        $dayOpen = substr($dayHours['open_time'] ?? '09:00', 0, 5);
                        $dayClose = substr($dayHours['close_time'] ?? '21:00', 0, 5);

                        if ($earliestOpen === null || $dayOpen < $earliestOpen) {
                            $earliestOpen = $dayOpen;
                        }
                        if ($latestClose === null || $dayClose > $latestClose) {
                            $latestClose = $dayClose;
                        }
                    }
                }

                if ($earliestOpen && $latestClose) {
                    $openTime = $earliestOpen;
                    $closeTime = $latestClose;
                }
            }
        }

        // 店舗の予約枠間隔を取得（デフォルト30分）
        $slotInterval = $store->reservation_slot_duration ?? 30;

        $start = Carbon::createFromTimeString($openTime);
        $end = Carbon::createFromTimeString($closeTime);

        while ($start < $end) {
            $slots[] = $start->format('H:i');
            $start->addMinutes($slotInterval);
        }

        return $slots;
    }

    private function getAvailability($store, $menu, $dates, $timeSlots, $staffId = null, $excludeReservationId = null)
    {
        $availability = [];

        // すべての日付の予約を一度に取得
        $dateStrings = collect($dates)->pluck('date')->map(fn($date) => $date->format('Y-m-d'));

        $reservationsQuery = Reservation::whereIn('reservation_date', $dateStrings)
            ->where('store_id', $store->id)
            ->whereNotIn('status', ['cancelled', 'canceled']);

        if ($excludeReservationId) {
            $reservationsQuery->where('id', '!=', $excludeReservationId);
        }

        if ($staffId) {
            $reservationsQuery->where('staff_id', $staffId);
        }

        $reservations = $reservationsQuery->get()->groupBy(function($reservation) {
            return $reservation->reservation_date->format('Y-m-d');
        });

        // シフト情報を取得
        $shifts = collect();
        if ($store->use_staff_assignment) {
            $shifts = Shift::whereIn('shift_date', $dateStrings)
                ->where('store_id', $store->id)
                ->get()
                ->groupBy(function($shift) {
                    return $shift->shift_date->format('Y-m-d');
                });
        }

        foreach ($dates as $date) {
            $dateStr = $date['date']->format('Y-m-d');
            $dayOfWeek = $date['date']->dayOfWeek;
            $dayName = strtolower($date['date']->format('l'));

            $dayReservations = $reservations->get($dateStr, collect());

            // 営業時間を取得
            $businessHours = $store->business_hours ?? [];
            $isBusinessDay = true;
            $openTime = '09:00';
            $closeTime = '21:00';

            if (is_array($businessHours)) {
                $dayHours = collect($businessHours)->firstWhere('day', $dayName);
                if (!$dayHours || ($dayHours['is_closed'] ?? false)) {
                    $isBusinessDay = false;
                } else {
                    $openTime = substr($dayHours['open_time'] ?? '09:00', 0, 5);
                    $closeTime = substr($dayHours['close_time'] ?? '21:00', 0, 5);
                }
            }

            foreach ($timeSlots as $slot) {
                if (!$isBusinessDay || $date['is_past']) {
                    $availability[$dateStr][$slot] = false;
                    continue;
                }

                $slotTime = Carbon::parse($date['date']->format('Y-m-d') . ' ' . $slot);
                $slotEnd = $slotTime->copy()->addMinutes($menu->duration);

                // 管理画面では過去の時間も選択可能（当日の過去時間も含む）

                // 営業時間チェック
                if ($slot < $openTime || $slotEnd->format('H:i') > $closeTime) {
                    $availability[$dateStr][$slot] = false;
                    continue;
                }

                // スタッフ指定の場合の重複チェック
                if ($staffId) {
                    $hasConflict = $dayReservations->filter(function($reservation) use ($slotTime, $slotEnd) {
                        $reservationStart = Carbon::parse($reservation->start_time);
                        $reservationEnd = Carbon::parse($reservation->end_time);

                        return (
                            ($slotTime->gte($reservationStart) && $slotTime->lt($reservationEnd)) ||
                            ($slotEnd->gt($reservationStart) && $slotEnd->lte($reservationEnd)) ||
                            ($slotTime->lte($reservationStart) && $slotEnd->gte($reservationEnd))
                        );
                    })->count() > 0;

                    if ($hasConflict) {
                        $availability[$dateStr][$slot] = false;
                        continue;
                    }
                }

                $availability[$dateStr][$slot] = true;
            }
        }

        return $availability;
    }

    private function checkSlotAvailability($store, $date, $startTime, $endTime, $staffId = null, $excludeReservationId = null)
    {
        $dayName = strtolower(Carbon::parse($date)->format('l'));

        // 営業時間チェック
        $businessHours = $store->business_hours ?? [];
        if (is_array($businessHours)) {
            $dayHours = collect($businessHours)->firstWhere('day', $dayName);
            if (!$dayHours || ($dayHours['is_closed'] ?? false)) {
                return ['available' => false, 'message' => 'この日は定休日です'];
            }

            $openTime = substr($dayHours['open_time'] ?? '09:00', 0, 5);
            $closeTime = substr($dayHours['close_time'] ?? '21:00', 0, 5);

            if ($startTime < $openTime || $endTime > $closeTime) {
                return ['available' => false, 'message' => '営業時間外です'];
            }
        }

        // 重複チェック
        $conflictQuery = Reservation::where('store_id', $store->id)
            ->where('reservation_date', $date)
            ->whereNotIn('status', ['cancelled', 'canceled']);

        if ($excludeReservationId) {
            $conflictQuery->where('id', '!=', $excludeReservationId);
        }

        if ($staffId) {
            $conflictQuery->where('staff_id', $staffId);
        }

        $hasConflict = $conflictQuery->where(function($query) use ($startTime, $endTime) {
            $query->where(function($q) use ($startTime) {
                $q->where('start_time', '<=', $startTime)
                  ->where('end_time', '>', $startTime);
            });
            $query->orWhere(function($q) use ($endTime) {
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>=', $endTime);
            });
            $query->orWhere(function($q) use ($startTime, $endTime) {
                $q->where('start_time', '>=', $startTime)
                  ->where('end_time', '<=', $endTime);
            });
        })->exists();

        if ($hasConflict) {
            return ['available' => false, 'message' => '選択された時間帯は既に予約が入っています'];
        }

        return ['available' => true, 'message' => '予約可能です'];
    }

    private function getDayInJapanese($dayOfWeek)
    {
        $days = ['日', '月', '火', '水', '木', '金', '土'];
        return $days[$dayOfWeek];
    }
}