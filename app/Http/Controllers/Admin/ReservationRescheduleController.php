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
        // メニュー情報をEager Load
        $reservation->load('menu');

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
        } elseif ($user->hasRole(['manager', 'staff'])) {
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
            $reservation->id, // 現在の予約IDを除外
            $reservation // 元の予約情報を渡す
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
            'reservation_date' => 'required|date',
            'start_time' => 'required',
            'staff_id' => 'nullable|exists:users,id',
        ], [
            'reservation_date.required' => '予約日を選択してください',
            'reservation_date.date' => '正しい日付を入力してください',
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
        if ($user->hasRole(['manager', 'staff']) && $user->store_id !== $store->id) {
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

            // 空き状況チェックと自動席割り当て（時刻をHH:MM:SS形式で渡す）
            $availability = $this->checkSlotAvailabilityWithSeatAssignment(
                $store,
                $validated['reservation_date'],
                $validated['start_time'] . ':00',  // HH:MM → HH:MM:SS
                $endTime->format('H:i:s'),         // H:i:s で秒まで含める
                $validated['staff_id'],
                $reservation->id
            );

            if (!$availability['available']) {
                return back()->withErrors(['error' => $availability['message']]);
            }

            // 予約を更新（店舗とメニューは変更しない）
            // サブ枠からの変更の場合、常にメイン枠に移動する
            $updateData = [
                'reservation_date' => $validated['reservation_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $endTime->format('H:i:s'),
                'staff_id' => $validated['staff_id'],
                'line_type' => 'main',  // 常にメイン枠に変更
                'is_sub' => false,      // サブフラグをオフ
                'updated_at' => now(),
            ];
            
            // 営業時間モードの場合は自動割り当てされた席番号を設定
            if (!$store->use_staff_assignment && isset($availability['assigned_seat'])) {
                $updateData['seat_number'] = $availability['assigned_seat'];
                $updateData['line_number'] = $availability['assigned_seat'];
                
                \Log::info('🔄 日程変更: 席自動割り当て', [
                    'reservation_id' => $reservation->id,
                    'original_seat' => $reservation->seat_number,
                    'new_seat' => $availability['assigned_seat'],
                    'date' => $validated['reservation_date'],
                    'time' => $validated['start_time']
                ]);
            }

            $reservation->update($updateData);

            // サブスクリプションIDの再評価（店舗やメニューは変更されていないが、未設定の場合は設定）
            $binder = app(\App\Services\ReservationSubscriptionBinder::class);
            $binder->bindModel($reservation->fresh());

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

    private function getAvailability($store, $menu, $dates, $timeSlots, $staffId = null, $excludeReservationId = null, $originalReservation = null)
    {
        $availability = [];

        // すべての日付の予約を一度に取得
        $dateStrings = collect($dates)->pluck('date')->map(fn($date) => $date->format('Y-m-d'));

        // SQLiteではreservation_dateが"2025-10-02 00:00:00"形式で保存されているため、DATE()関数を使用
        $reservationsQuery = Reservation::where('store_id', $store->id)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->where(function($query) use ($dateStrings) {
                foreach ($dateStrings as $dateStr) {
                    $query->orWhereRaw('DATE(reservation_date) = ?', [$dateStr]);
                }
            });

        if ($excludeReservationId) {
            $reservationsQuery->where('id', '<>', $excludeReservationId);
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

        // ブロックされた時間帯を取得
        $blockedPeriodsQuery = BlockedTimePeriod::where('store_id', $store->id)
            ->whereIn(DB::raw('DATE(blocked_date)'), $dateStrings);

        $blockedPeriods = $blockedPeriodsQuery->get()->groupBy(function($blocked) {
            return Carbon::parse($blocked->blocked_date)->format('Y-m-d');
        });

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
                if (!$isBusinessDay) {
                    $availability[$dateStr][$slot] = false;
                    continue;
                }

                $slotTime = Carbon::parse($date['date']->format('Y-m-d') . ' ' . $slot);
                $slotEnd = $slotTime->copy()->addMinutes($menu->duration);

                // 営業時間チェック - 終了時間が営業終了時刻を超えないかチェック
                $openTimeCarbon = Carbon::parse($date['date']->format('Y-m-d') . ' ' . $openTime);
                $closeTimeCarbon = Carbon::parse($date['date']->format('Y-m-d') . ' ' . $closeTime);

                if ($slotTime->lt($openTimeCarbon) || $slotEnd->gt($closeTimeCarbon)) {
                    $availability[$dateStr][$slot] = false;
                    continue;
                }

                // ブロック時間帯チェック
                $dayBlockedPeriods = $blockedPeriods->get($dateStr, collect());
                $isBlocked = false;

                foreach ($dayBlockedPeriods as $blocked) {
                    $blockStart = Carbon::parse($dateStr . ' ' . $blocked->start_time);
                    $blockEnd = Carbon::parse($dateStr . ' ' . $blocked->end_time);

                    // 全体ブロック（line_typeがnull）の場合は必ずブロック
                    if ($blocked->line_type === null) {
                        if (
                            ($slotTime->gte($blockStart) && $slotTime->lt($blockEnd)) ||
                            ($slotEnd->gt($blockStart) && $slotEnd->lte($blockEnd)) ||
                            ($slotTime->lte($blockStart) && $slotEnd->gte($blockEnd))
                        ) {
                            $isBlocked = true;
                            break;
                        }
                    }

                    // 常にメイン枠のブロックのみをチェック
                    // （メイン→メイン、サブ→メインの移動を可能にする）
                    if ($originalReservation) {
                        if ($blocked->line_type === 'main') {
                            if (
                                ($slotTime->gte($blockStart) && $slotTime->lt($blockEnd)) ||
                                ($slotEnd->gt($blockStart) && $slotEnd->lte($blockEnd)) ||
                                ($slotTime->lte($blockStart) && $slotEnd->gte($blockEnd))
                            ) {
                                $isBlocked = true;
                                break;
                            }
                        }
                    }

                    // スタッフ指定がある場合、そのスタッフのブロックをチェック
                    if ($staffId && $blocked->line_type === 'staff' && $blocked->staff_id == $staffId) {
                        if (
                            ($slotTime->gte($blockStart) && $slotTime->lt($blockEnd)) ||
                            ($slotEnd->gt($blockStart) && $slotEnd->lte($blockEnd)) ||
                            ($slotTime->lte($blockStart) && $slotEnd->gte($blockEnd))
                        ) {
                            $isBlocked = true;
                            break;
                        }
                    }
                }

                if ($isBlocked) {
                    $availability[$dateStr][$slot] = false;
                    continue;
                }

                // 重複チェック（スタッフ指定の有無に関わらず）
                $relevantReservations = $staffId
                    ? $dayReservations->where('staff_id', $staffId)
                    : $dayReservations;

                // 常にメイン枠の予約のみをチェック
                // （メイン→メイン、サブ→メインの移動を可能にする）
                if ($originalReservation) {
                    $relevantReservations = $relevantReservations->filter(function($reservation) {
                        // メイン枠の予約のみを対象とする
                        $reservationIsSub = $reservation->is_sub || $reservation->line_type === 'sub';
                        return !$reservationIsSub;
                    });
                }

                $conflictingReservations = $relevantReservations->filter(function($reservation) use ($slotTime, $slotEnd, $dateStr) {
                    // 時間文字列を日付と結合してからパース
                    $reservationStart = Carbon::parse($dateStr . ' ' . $reservation->start_time);
                    $reservationEnd = Carbon::parse($dateStr . ' ' . $reservation->end_time);

                    // 正しい重複判定: 新予約のstart < 既存予約のend AND 新予約のend > 既存予約のstart
                    // ピッタリ同じ時刻（17:00-18:00 と 18:00-19:00）は重複しない
                    return $slotTime->lt($reservationEnd) && $slotEnd->gt($reservationStart);
                });

                // 席数を考慮した判定（営業時間モードの場合）
                $maxSeats = $store->main_lines_count ?? 1;
                $conflictCount = $conflictingReservations->count();

                if ($conflictCount >= $maxSeats) {
                    $availability[$dateStr][$slot] = false;
                    continue;
                }

                $availability[$dateStr][$slot] = true;
            }
        }

        return $availability;
    }

    private function checkSlotAvailabilityWithSeatAssignment($store, $date, $startTime, $endTime, $staffId = null, $excludeReservationId = null)
    {
        // 営業時間モードの場合：空き席を自動で探す
        if (!$store->use_staff_assignment) {
            $maxSeats = $store->main_lines_count ?? 1;
            $availableSeats = [];
            
            \Log::info('🔍 日程変更: 席空き状況チェック開始', [
                'date' => $date,
                'time' => $startTime . '-' . $endTime,
                'max_seats' => $maxSeats,
                'exclude_reservation_id' => $excludeReservationId
            ]);
            
            for ($seatNumber = 1; $seatNumber <= $maxSeats; $seatNumber++) {
                // この席の重複チェック
                $hasConflict = Reservation::where('store_id', $store->id)
                    ->whereDate('reservation_date', $date)
                    ->where('seat_number', $seatNumber)
                    ->where('is_sub', false)
                    ->whereNotIn('status', ['cancelled', 'canceled'])
                    ->where('id', '!=', $excludeReservationId)
                    ->where(function($query) use ($startTime, $endTime) {
                        // time()関数で時刻フォーマットを統一
                        $query->whereRaw('time(start_time) < time(?)', [$endTime])
                              ->whereRaw('time(end_time) > time(?)', [$startTime]);
                    })
                    ->exists();
                
                if (!$hasConflict) {
                    $availableSeats[] = $seatNumber;
                    \Log::info("  席{$seatNumber}: ✅ 空き");
                } else {
                    \Log::info("  席{$seatNumber}: ❌ 重複あり");
                }
            }
            
            // 空き席がある場合は最初の席を割り当て
            if (!empty($availableSeats)) {
                return [
                    'available' => true,
                    'message' => '予約可能です',
                    'assigned_seat' => $availableSeats[0]
                ];
            }
            
            return ['available' => false, 'message' => '選択された時間帯は満席です'];
        }
        
        // スタッフシフトモードの場合は従来の処理
        return $this->checkSlotAvailability($store, $date, $startTime, $endTime, $staffId, $excludeReservationId);
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

        // ブロック時間帯チェック
        $blockedPeriodsQuery = BlockedTimePeriod::where('store_id', $store->id)
            ->whereDate('blocked_date', $date);

        // 全体ブロック（line_typeがnull）をチェック
        $hasGlobalBlock = (clone $blockedPeriodsQuery)
            ->whereNull('line_type')
            ->where(function($query) use ($startTime, $endTime) {
                $query->where(function($q) use ($startTime) {
                    // time()関数で時刻フォーマットを統一
                    $q->whereRaw('time(start_time) <= time(?)', [$startTime])
                      ->whereRaw('time(end_time) > time(?)', [$startTime]);
                })
                ->orWhere(function($q) use ($endTime) {
                    // time()関数で時刻フォーマットを統一
                    $q->whereRaw('time(start_time) < time(?)', [$endTime])
                      ->whereRaw('time(end_time) >= time(?)', [$endTime]);
                })
                ->orWhere(function($q) use ($startTime, $endTime) {
                    $q->where('start_time', '>=', $startTime)
                      ->where('end_time', '<=', $endTime);
                });
            })->exists();

        if ($hasGlobalBlock) {
            return ['available' => false, 'message' => 'この時間帯はブロックされています'];
        }

        // スタッフ指定がある場合は、そのスタッフのライン専用ブロックをチェック
        if ($staffId) {
            $hasStaffLineBlock = (clone $blockedPeriodsQuery)
                ->where('line_type', 'staff')
                ->where('staff_id', $staffId)
                ->where(function($query) use ($startTime, $endTime) {
                    $query->where(function($q) use ($startTime) {
                        // time()関数で時刻フォーマットを統一
                        $q->whereRaw('time(start_time) <= time(?)', [$startTime])
                          ->whereRaw('time(end_time) > time(?)', [$startTime]);
                    })
                    ->orWhere(function($q) use ($endTime) {
                        // time()関数で時刻フォーマットを統一
                        $q->whereRaw('time(start_time) < time(?)', [$endTime])
                          ->whereRaw('time(end_time) >= time(?)', [$endTime]);
                    })
                    ->orWhere(function($q) use ($startTime, $endTime) {
                        $q->where('start_time', '>=', $startTime)
                          ->where('end_time', '<=', $endTime);
                    });
                })->exists();

            if ($hasStaffLineBlock) {
                return ['available' => false, 'message' => 'このスタッフのラインはブロックされています'];
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

        // 時間重複チェック（境界を含まない: 14:30-15:30と15:30-17:00は重複しない）
        // time()関数で時刻フォーマットを統一
        $conflictCount = $conflictQuery->where(function($query) use ($startTime, $endTime) {
            $query->whereRaw('time(start_time) < time(?)', [$endTime])
                  ->whereRaw('time(end_time) > time(?)', [$startTime]);
        })->count();

        // 席数を考慮した判定（営業時間モードの場合）
        // スタッフ指定がない場合のみ席数をチェック
        if (!$staffId) {
            $maxSeats = $store->main_lines_count ?? 1;
            if ($conflictCount >= $maxSeats) {
                return ['available' => false, 'message' => '選択された時間帯は満席です'];
            }
        } else {
            // スタッフ指定がある場合は、そのスタッフの予約が1件でもあればNG
            if ($conflictCount > 0) {
                return ['available' => false, 'message' => '選択された時間帯は既に予約が入っています'];
            }
        }

        return ['available' => true, 'message' => '予約可能です'];
    }

    private function getDayInJapanese($dayOfWeek)
    {
        $days = ['日', '月', '火', '水', '木', '金', '土'];
        return $days[$dayOfWeek];
    }
}