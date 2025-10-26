<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Events\ReservationCancelled;
use App\Events\ReservationChanged;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Reservation $reservation)
    {
        $reservation->load(['customer', 'menu', 'store', 'reservationOptions.menuOption']);

        return response()->json($reservation);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Reservation $reservation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Reservation $reservation)
    {
        //
    }

    /**
     * 管理者向け予約キャンセル
     */
    public function adminCancelReservation(Request $request, $id)
    {
        $reservation = Reservation::find($id);

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => '予約が見つかりません'
            ], 404);
        }

        // キャンセル可能かチェック
        if (in_array($reservation->status, ['cancelled', 'completed', 'no_show'])) {
            return response()->json([
                'success' => false,
                'message' => 'この予約はキャンセルできません'
            ], 400);
        }

        // キャンセル実行
        $reservation->update([
            'status' => 'cancelled',
            'cancel_reason' => $request->input('cancel_reason', '管理者によるキャンセル'),
            'cancelled_at' => now()
        ]);

        // 顧客のキャンセル回数を更新
        if ($reservation->customer) {
            $reservation->customer->increment('cancellation_count');
            $reservation->customer->update(['last_cancelled_at' => now()]);
        }

        // 回数券を使用（キャンセルでも使用扱い）
        if ($reservation->customer_ticket_id) {
            $ticket = \App\Models\CustomerTicket::find($reservation->customer_ticket_id);
            if ($ticket && $ticket->canUse()) {
                $ticket->use($reservation->id);
                \Log::info('予約キャンセル：回数券使用', [
                    'reservation_id' => $reservation->id,
                    'ticket_id' => $ticket->id,
                    'remaining_count' => $ticket->fresh()->remaining_count,
                    'reason' => 'キャンセルでも使用扱い'
                ]);
            }
        }

        // キャンセル通知を送信
        event(new ReservationCancelled($reservation));

        return response()->json([
            'success' => true,
            'message' => '予約をキャンセルしました',
            'data' => $reservation
        ]);
    }

    /**
     * 管理者向け予約完了
     */
    public function adminCompleteReservation(Request $request, $id)
    {
        $reservation = Reservation::find($id);

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => '予約が見つかりません'
            ], 404);
        }

        if ($reservation->status !== 'booked') {
            return response()->json([
                'success' => false,
                'message' => 'この予約は完了にできません'
            ], 400);
        }

        // 完了処理
        $reservation->update(['status' => 'completed']);

        // サブスクリプション利用回数を更新
        $customer = $reservation->customer;
        if ($customer) {
            $subscription = $customer->activeSubscription;
            if ($subscription) {
                $subscription->recordVisit();
            }
        }

        // 回数券を使用
        if ($reservation->customer_ticket_id) {
            $ticket = \App\Models\CustomerTicket::find($reservation->customer_ticket_id);
            if ($ticket && $ticket->canUse()) {
                $ticket->use($reservation->id);
                \Log::info('予約完了：回数券使用', [
                    'reservation_id' => $reservation->id,
                    'ticket_id' => $ticket->id,
                    'remaining_count' => $ticket->fresh()->remaining_count
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => '予約を完了しました',
            'data' => $reservation
        ]);
    }

    /**
     * 管理者向け来店なし設定
     */
    public function adminNoShowReservation(Request $request, $id)
    {
        $reservation = Reservation::find($id);

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => '予約が見つかりません'
            ], 404);
        }

        if ($reservation->status !== 'booked') {
            return response()->json([
                'success' => false,
                'message' => 'この予約は来店なしにできません'
            ], 400);
        }

        $reservation->update(['status' => 'no_show']);

        return response()->json([
            'success' => true,
            'message' => '予約を来店なしにしました',
            'data' => $reservation
        ]);
    }

    /**
     * 管理者向け予約復元
     */
    public function adminRestoreReservation(Request $request, $id)
    {
        $reservation = Reservation::find($id);

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => '予約が見つかりません'
            ], 404);
        }

        if (!in_array($reservation->status, ['cancelled', 'no_show'])) {
            return response()->json([
                'success' => false,
                'message' => 'この予約は復元できません'
            ], 400);
        }

        $reservation->update(['status' => 'booked']);

        return response()->json([
            'success' => true,
            'message' => '予約を復元しました',
            'data' => $reservation
        ]);
    }

    /**
     * 管理者向けサブラインへ移動
     */
    public function adminMoveToSubLine(Request $request, $id)
    {
        $reservation = Reservation::find($id);

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => '予約が見つかりません'
            ], 404);
        }

        if ($reservation->line_type !== 'main' || $reservation->status !== 'booked') {
            return response()->json([
                'success' => false,
                'message' => 'この予約はサブラインに移動できません'
            ], 400);
        }

        $reservation->moveToSubLine();

        return response()->json([
            'success' => true,
            'message' => '予約をサブラインに移動しました',
            'data' => $reservation
        ]);
    }

    /**
     * 管理者向けメインラインへ移動
     */
    public function adminMoveToMainLine(Request $request, $id)
    {
        $reservation = Reservation::find($id);

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => '予約が見つかりません'
            ], 404);
        }

        if ($reservation->line_type !== 'sub' || $reservation->status !== 'booked') {
            return response()->json([
                'success' => false,
                'message' => 'この予約はメインラインに戻せません'
            ], 400);
        }

        $reservation->moveToMainLine();

        return response()->json([
            'success' => true,
            'message' => '予約をメインラインに戻しました',
            'data' => $reservation
        ]);
    }

    /**
     * 予約作成（顧客用）
     */
    public function createReservation(Request $request)
    {
        $customer = $request->user();
        
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'menu_id' => 'required|exists:menus,id',
            'reservation_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required',
            'is_subscription' => 'boolean',
            'customer_ticket_id' => 'nullable|exists:customer_tickets,id',
            'customer_subscription_id' => 'nullable|exists:customer_subscriptions,id',
            'option_ids' => 'nullable|array'
        ]);

        // メニュー情報取得
        $menu = \App\Models\Menu::find($validated['menu_id']);

        // 予約番号生成
        $reservationNumber = 'R' . date('YmdHis') . rand(100, 999);

        // 終了時間計算（メニュー + オプションの合計時間）
        $startTime = \Carbon\Carbon::parse($validated['reservation_date'] . ' ' . $validated['start_time']);
        $totalDuration = $menu->duration_minutes ?? 60;

        // オプションの所要時間を加算
        if (!empty($validated['option_ids'])) {
            $optionsDuration = \App\Models\MenuOption::whereIn('id', $validated['option_ids'])
                ->sum('duration_minutes');
            $totalDuration += $optionsDuration;
        }

        $endTime = $startTime->copy()->addMinutes($totalDuration);

        // 予約作成データ準備
        $reservationData = [
            'reservation_number' => $reservationNumber,
            'customer_id' => $customer->id,
            'store_id' => $validated['store_id'],
            'menu_id' => $validated['menu_id'],
            'reservation_date' => $validated['reservation_date'], // 日付のみ（例：2025-09-11）
            'start_time' => $validated['start_time'], // 時刻のみ（例：14:00:00）
            'end_time' => $endTime->format('H:i:s'), // 時刻のみ
            'total_amount' => ($validated['is_subscription'] ?? false) ? 0 : $menu->price,
            'status' => 'booked',
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'first_name_kana' => $customer->first_name_kana,
            'last_name_kana' => $customer->last_name_kana,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'source' => 'online', // 既存システムと同じ
            'notes' => 'サブスクリプション予約'
        ];

        // 回数券IDがある場合は設定
        if (!empty($validated['customer_ticket_id'])) {
            $reservationData['customer_ticket_id'] = $validated['customer_ticket_id'];
        }

        // サブスクリプションIDがある場合は設定
        if (!empty($validated['customer_subscription_id'])) {
            $reservationData['customer_subscription_id'] = $validated['customer_subscription_id'];
        }

        // 予約作成（既存システムと同じフィールド形式）
        $reservation = Reservation::create($reservationData);

        // オプションを追加
        if (!empty($validated['option_ids'])) {
            foreach ($validated['option_ids'] as $optionId) {
                $option = \App\Models\MenuOption::find($optionId);
                if ($option) {
                    $reservation->reservationOptions()->create([
                        'menu_option_id' => $optionId,
                        'name' => $option->name,
                        'price' => $option->price,
                        'duration_minutes' => $option->duration_minutes
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => '予約が完了しました',
            'data' => $reservation->load(['store', 'menu', 'reservationOptions'])
        ], 201);
    }

    /**
     * 顧客の予約統計取得
     */
    public function customerReservationStats(Request $request)
    {
        $customer = $request->user();

        $totalReservations = Reservation::where('customer_id', $customer->id)->count();
        $upcomingReservations = Reservation::where('customer_id', $customer->id)
            ->where('status', 'booked')
            ->where('reservation_date', '>=', now()->format('Y-m-d'))
            ->count();
        $completedReservations = Reservation::where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->count();

        return response()->json([
            'total' => $totalReservations,
            'upcoming' => $upcomingReservations,
            'completed' => $completedReservations
        ]);
    }

    /**
     * 顧客の予約履歴取得
     */
    public function customerReservations(Request $request)
    {
        $customer = $request->user();

        // 同じ電話番号を持つ全顧客IDを取得
        $customerIds = \App\Models\Customer::where('phone', $customer->phone)
            ->pluck('id')
            ->toArray();

        // 予約を取得（店舗IDがある場合のみフィルタリング）
        $query = Reservation::whereIn('customer_id', $customerIds);

        // リクエストヘッダーまたはクエリパラメータから店舗IDを取得
        $filterStoreId = $request->header('X-Store-Id') ?? $request->input('store_id') ?? $customer->store_id;

        // 店舗IDが設定されている場合のみ店舗でフィルタリング
        if ($filterStoreId) {
            $query->where('store_id', $filterStoreId);
        }

        $reservations = $query
            ->with(['store', 'menu', 'staff'])
            ->orderBy('reservation_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get()
            ->map(function ($reservation) {
                // タイムゾーンの問題を避けるため、日付を文字列として返す
                $reservationArray = $reservation->toArray();
                
                // 日付を安全な形式に変換（タイムゾーン変換を避ける）
                if ($reservation->reservation_date instanceof \Carbon\Carbon) {
                    $reservationArray['reservation_date'] = $reservation->reservation_date->format('Y-m-d H:i:s');
                }

                // optionMenusを安全に追加
                try {
                    $optionMenus = $reservation->getOptionMenusSafely();
                    $reservationArray['option_menus'] = $optionMenus->map(function ($option) {
                        return [
                            'id' => $option->id,
                            'name' => $option->name,
                            'pivot' => [
                                'price' => $option->pivot->price ?? 0,
                                'duration' => $option->pivot->duration ?? 0,
                            ]
                        ];
                    })->toArray();
                } catch (\Exception $e) {
                    \Log::error('Error adding option_menus to API response', [
                        'reservation_id' => $reservation->id,
                        'error' => $e->getMessage()
                    ]);
                    $reservationArray['option_menus'] = [];
                }

                return (object) $reservationArray;
            });

        return response()->json([
            'message' => '予約履歴を取得しました',
            'data' => $reservations
        ]);
    }

    /**
     * 顧客の予約詳細取得
     */
    public function customerReservationDetail(Request $request, $id)
    {
        $customer = $request->user();
        
        $reservation = Reservation::where('customer_id', $customer->id)
            ->where('id', $id)
            ->with(['store', 'menu', 'staff', 'medicalRecords'])
            ->first();

        if (!$reservation) {
            return response()->json([
                'message' => '予約が見つかりません'
            ], 404);
        }

        // optionMenusを安全に追加
        $reservationData = $reservation->toArray();
        try {
            $optionMenus = $reservation->getOptionMenusSafely();
            $reservationData['option_menus'] = $optionMenus->map(function ($option) {
                return [
                    'id' => $option->id,
                    'name' => $option->name,
                    'pivot' => [
                        'price' => $option->pivot->price ?? 0,
                        'duration' => $option->pivot->duration ?? 0,
                    ]
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('Error adding option_menus to detail API response', [
                'reservation_id' => $id,
                'error' => $e->getMessage()
            ]);
            $reservationData['option_menus'] = [];
        }

        return response()->json([
            'message' => '予約詳細を取得しました',
            'data' => $reservationData
        ]);
    }

    /**
     * 予約キャンセル
     */
    public function cancelReservation(Request $request, $id)
    {
        $customer = $request->user();
        
        $reservation = Reservation::where('customer_id', $customer->id)
            ->where('id', $id)
            ->with(['store'])
            ->first();

        if (!$reservation) {
            return response()->json([
                'message' => '予約が見つかりません'
            ], 404);
        }

        // キャンセル可能かチェック
        if (in_array($reservation->status, ['cancelled', 'completed', 'no_show'])) {
            return response()->json([
                'message' => 'この予約はキャンセルできません'
            ], 400);
        }

        // 24時間前チェック
        // reservation_dateから日付部分、start_timeから時刻部分を取得
        $dateStr = is_string($reservation->reservation_date) ? 
            $reservation->reservation_date : 
            $reservation->reservation_date->format('Y-m-d');
        
        $timeStr = is_string($reservation->start_time) ? 
            $reservation->start_time : 
            $reservation->start_time->format('H:i:s');
            
        // 日付部分のみを取得（タイムスタンプが含まれている場合）
        if (strpos($dateStr, ' ') !== false) {
            $dateStr = explode(' ', $dateStr)[0];
        }
        
        // 時刻部分のみを取得（日付が含まれている場合）
        if (strpos($timeStr, ' ') !== false) {
            $parts = explode(' ', $timeStr);
            $timeStr = end($parts);
        }
        
        $reservationDateTime = \Carbon\Carbon::parse($dateStr . ' ' . $timeStr);
        $now = \Carbon\Carbon::now();
        $hoursUntilReservation = $now->diffInHours($reservationDateTime, false);
        
        if ($hoursUntilReservation < 24) {
            return response()->json([
                'success' => false,
                'message' => '予約の24時間前を過ぎています',
                'require_phone_contact' => true,
                'store_phone' => $reservation->store->phone,
                'store_name' => $reservation->store->name
            ], 400);
        }

        // キャンセル実行
        $reservation->update([
            'status' => 'cancelled',
            'cancel_reason' => $request->input('cancel_reason', '顧客都合'),
            'cancelled_at' => now()
        ]);

        // 顧客のキャンセル回数を更新
        $customer->increment('cancellation_count');
        $customer->update(['last_cancelled_at' => now()]);

        // キャンセル通知を送信
        event(new ReservationCancelled($reservation));

        return response()->json([
            'success' => true,
            'message' => '予約をキャンセルしました',
            'data' => $reservation
        ]);
    }

    /**
     * 予約日時変更
     */
    public function changeReservationDate(Request $request, $id)
    {
        $customer = $request->user();
        
        $reservation = Reservation::where('customer_id', $customer->id)
            ->where('id', $id)
            ->with(['store', 'menu'])
            ->first();

        if (!$reservation) {
            return response()->json([
                'message' => '予約が見つかりません'
            ], 404);
        }

        // 変更可能かチェック
        if (in_array($reservation->status, ['cancelled', 'completed', 'no_show'])) {
            return response()->json([
                'message' => 'この予約は変更できません'
            ], 400);
        }

        // バリデーション
        $validated = $request->validate([
            'new_date' => 'required|date|after_or_equal:today',
            'new_time' => 'required'
        ]);

        // 24時間前チェック（元の予約時間）
        $dateStr = is_string($reservation->reservation_date) ? 
            $reservation->reservation_date : 
            $reservation->reservation_date->format('Y-m-d');
        
        $timeStr = is_string($reservation->start_time) ? 
            $reservation->start_time : 
            $reservation->start_time->format('H:i:s');
            
        if (strpos($dateStr, ' ') !== false) {
            $dateStr = explode(' ', $dateStr)[0];
        }
        
        if (strpos($timeStr, ' ') !== false) {
            $parts = explode(' ', $timeStr);
            $timeStr = end($parts);
        }
        
        $reservationDateTime = \Carbon\Carbon::parse($dateStr . ' ' . $timeStr);
        $now = \Carbon\Carbon::now();
        $hoursUntilReservation = $now->diffInHours($reservationDateTime, false);
        
        if ($hoursUntilReservation < 24) {
            return response()->json([
                'success' => false,
                'message' => '予約の24時間前を過ぎています。お電話でお問い合わせください',
                'require_phone_contact' => true,
                'store_phone' => $reservation->store->phone,
                'store_name' => $reservation->store->name
            ], 400);
        }

        // 新しい終了時間を計算
        $newStartTime = \Carbon\Carbon::parse($validated['new_date'] . ' ' . $validated['new_time']);
        $duration = $reservation->menu->duration_minutes ?? 60;
        $newEndTime = $newStartTime->copy()->addMinutes($duration);

        // 空き状況チェック（元の予約と同じline_type/is_subの枠のみ）
        $conflictingReservations = Reservation::where('store_id', $reservation->store_id)
            ->whereDate('reservation_date', $validated['new_date'])
            ->whereNotIn('status', ['cancelled', 'canceled', 'no_show'])
            ->where('id', '!=', $reservation->id) // 自分の予約は除外
            ->where(function($query) use ($validated, $newEndTime) {
                // 時間重複チェック
                $query->where(function($q) use ($validated, $newEndTime) {
                    $q->where('start_time', '<', $newEndTime->format('H:i:s'))
                      ->where('end_time', '>', $validated['new_time']);
                });
            })
            ->get();

        // 元の予約と同じline_type（メイン/サブ）の予約のみをフィルタ
        $originalIsSub = $reservation->is_sub || $reservation->line_type === 'sub';
        $conflictingReservations = $conflictingReservations->filter(function($r) use ($originalIsSub) {
            $reservationIsSub = $r->is_sub || $r->line_type === 'sub';
            return $originalIsSub === $reservationIsSub;
        });

        if ($conflictingReservations->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => '選択された時間帯は既に予約が入っています。別の時間をお選びください。'
            ], 400);
        }

        // 変更前の情報を保存（イベント用）
        $oldReservation = $reservation->replicate();

        // 変更を保存
        $reservation->update([
            'reservation_date' => $validated['new_date'],
            'start_time' => $validated['new_time'],
            'end_time' => $newEndTime->format('H:i:s')
        ]);

        // 顧客の変更回数を更新
        $customer->increment('change_count');

        // 変更通知を送信（正しい形式で）
        event(new ReservationChanged($oldReservation, $reservation));

        return response()->json([
            'success' => true,
            'message' => '予約日時を変更しました',
            'data' => $reservation
        ]);
    }

    /**
     * 予約変更
     */
    public function updateReservation(Request $request, $id)
    {
        $customer = $request->user();
        
        $reservation = Reservation::where('customer_id', $customer->id)
            ->where('id', $id)
            ->with(['store', 'menu'])
            ->first();

        if (!$reservation) {
            return response()->json([
                'message' => '予約が見つかりません'
            ], 404);
        }

        // 変更可能かチェック
        if (!in_array($reservation->status, ['confirmed', 'pending'])) {
            return response()->json([
                'message' => 'この予約は変更できません'
            ], 400);
        }

        // 24時間前チェック
        $reservationDateTime = \Carbon\Carbon::parse($reservation->reservation_date . ' ' . $reservation->start_time);
        $now = \Carbon\Carbon::now();
        $hoursUntilReservation = $now->diffInHours($reservationDateTime, false);
        
        if ($hoursUntilReservation < 24) {
            return response()->json([
                'success' => false,
                'message' => '予約の24時間前を過ぎています',
                'require_phone_contact' => true,
                'store_phone' => $reservation->store->phone,
                'store_name' => $reservation->store->name
            ], 400);
        }

        $validated = $request->validate([
            'reservation_date' => 'sometimes|date|after:today',
            'start_time' => 'sometimes|date_format:H:i:s',
            'menu_id' => 'sometimes|exists:menus,id'
        ]);

        // 変更前の予約情報を保存
        $oldReservation = $reservation->replicate();

        // 変更実行
        if (isset($validated['reservation_date'])) {
            $reservation->reservation_date = $validated['reservation_date'];
        }

        if (isset($validated['start_time'])) {
            $reservation->start_time = $validated['start_time'];
            $reservation->reservation_time = $validated['start_time'];

            // 終了時間も更新
            $startTime = \Carbon\Carbon::parse($reservation->reservation_date . ' ' . $validated['start_time']);
            $duration = $reservation->menu->duration_minutes ?? 60;
            $reservation->end_time = $startTime->copy()->addMinutes($duration);
        }

        if (isset($validated['menu_id'])) {
            $menu = \App\Models\Menu::find($validated['menu_id']);
            $reservation->menu_id = $menu->id;
            $reservation->total_amount = $menu->price;
        }

        // 日時変更がある場合は空き状況チェック
        if (isset($validated['reservation_date']) || isset($validated['start_time'])) {
            $checkDate = $reservation->reservation_date;
            $checkStartTime = $reservation->start_time;
            $checkEndTime = $reservation->end_time;

            $conflictingReservations = Reservation::where('store_id', $reservation->store_id)
                ->whereDate('reservation_date', $checkDate)
                ->whereNotIn('status', ['cancelled', 'canceled', 'no_show'])
                ->where('id', '!=', $reservation->id)
                ->where(function($query) use ($checkStartTime, $checkEndTime) {
                    $query->where(function($q) use ($checkStartTime, $checkEndTime) {
                        $q->where('start_time', '<', $checkEndTime)
                          ->where('end_time', '>', $checkStartTime);
                    });
                })
                ->get();

            // 元の予約と同じline_type（メイン/サブ）の予約のみをフィルタ
            $originalIsSub = $oldReservation->is_sub || $oldReservation->line_type === 'sub';
            $conflictingReservations = $conflictingReservations->filter(function($r) use ($originalIsSub) {
                $reservationIsSub = $r->is_sub || $r->line_type === 'sub';
                return $originalIsSub === $reservationIsSub;
            });

            if ($conflictingReservations->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => '選択された時間帯は既に予約が入っています。別の時間をお選びください。'
                ], 400);
            }
        }

        $reservation->save();
        
        // 顧客の変更回数を更新
        $customer->increment('change_count');
        
        // 予約変更通知を送信
        event(new ReservationChanged($oldReservation, $reservation));

        return response()->json([
            'success' => true,
            'message' => '予約を変更しました',
            'data' => $reservation->load(['store', 'menu'])
        ]);
    }

    /**
     * 管理者向けメニュー変更（オプション追加・削除にも対応）
     */
    public function adminChangeMenu(Request $request, $id)
    {
        $reservation = Reservation::with(['menu', 'store', 'reservationOptions'])->find($id);

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => '予約が見つかりません'
            ], 404);
        }

        // 完了・キャンセル済みの予約は変更不可
        if (in_array($reservation->status, ['completed', 'cancelled', 'canceled'])) {
            return response()->json([
                'success' => false,
                'message' => 'この予約のメニューは変更できません（ステータス: ' . $reservation->status . '）'
            ], 400);
        }

        $validated = $request->validate([
            'menu_id' => 'sometimes|exists:menus,id',
            'option_menu_ids' => 'sometimes|array',
            'option_menu_ids.*' => 'exists:menu_options,id'
        ]);

        $oldMenu = $reservation->menu;
        $oldOptions = $reservation->reservationOptions;

        // メニュー変更がある場合
        if (isset($validated['menu_id'])) {
            $newMenu = \App\Models\Menu::find($validated['menu_id']);

            // メニューが同じ店舗のものか確認
            if ($newMenu->store_id !== $reservation->store_id) {
                return response()->json([
                    'success' => false,
                    'message' => '異なる店舗のメニューには変更できません'
                ], 400);
            }

            $menuDuration = $newMenu->duration_minutes ?? 60;
            $menuPrice = $newMenu->price;
        } else {
            // メニュー変更がない場合は現在のメニューを使用
            $menuDuration = $oldMenu->duration_minutes ?? 60;
            $menuPrice = $oldMenu->price;
        }

        // オプションの合計時間と金額を計算
        $optionsDuration = 0;
        $optionsPrice = 0;
        $newOptions = [];

        if (isset($validated['option_menu_ids'])) {
            foreach ($validated['option_menu_ids'] as $optionId) {
                $option = \App\Models\MenuOption::find($optionId);
                if ($option) {
                    $optionsDuration += $option->duration_minutes ?? 0;
                    $optionsPrice += $option->price ?? 0;
                    $newOptions[] = $option;
                }
            }
        }

        // 合計時間を計算
        $totalDuration = $menuDuration + $optionsDuration;

        // 終了時間を再計算
        $startTime = \Carbon\Carbon::parse($reservation->reservation_date . ' ' . $reservation->start_time);
        $newEndTime = $startTime->copy()->addMinutes($totalDuration);

        // 時間重複チェック（新しいメニュー + オプションの合計時間で）
        $conflictingReservations = Reservation::where('store_id', $reservation->store_id)
            ->whereDate('reservation_date', $reservation->reservation_date)
            ->whereNotIn('status', ['cancelled', 'canceled', 'no_show'])
            ->where('id', '!=', $reservation->id)
            ->where(function($query) use ($reservation, $newEndTime) {
                $query->where(function($q) use ($reservation, $newEndTime) {
                    $q->where('start_time', '<', $newEndTime->format('H:i:s'))
                      ->where('end_time', '>', $reservation->start_time);
                });
            })
            ->get();

        // 同じline_type（メイン/サブ）の予約のみをフィルタ
        $originalIsSub = $reservation->is_sub || $reservation->line_type === 'sub';
        $conflictingReservations = $conflictingReservations->filter(function($r) use ($originalIsSub) {
            $reservationIsSub = $r->is_sub || $r->line_type === 'sub';
            return $originalIsSub === $reservationIsSub;
        });

        if ($conflictingReservations->count() > 0) {
            $conflictTimes = $conflictingReservations->map(function($r) {
                return $r->start_time . ' - ' . $r->end_time;
            })->join(', ');

            return response()->json([
                'success' => false,
                'message' => '変更後の終了時間が次の予約と被ってしまいます。',
                'details' => [
                    'new_end_time' => $newEndTime->format('H:i'),
                    'conflicting_times' => $conflictTimes,
                    'total_duration' => $totalDuration . '分',
                    'menu_duration' => $menuDuration . '分',
                    'options_duration' => $optionsDuration . '分'
                ]
            ], 400);
        }

        // データベース更新を開始
        \DB::beginTransaction();
        try {
            // メニュー変更を実行
            $updateData = [
                'end_time' => $newEndTime->format('H:i:s'),
                'total_amount' => $menuPrice + $optionsPrice
            ];

            if (isset($validated['menu_id'])) {
                $updateData['menu_id'] = $validated['menu_id'];
            }

            $reservation->update($updateData);

            // 既存のオプションを削除
            $reservation->reservationOptions()->delete();

            // 新しいオプションを追加
            if (!empty($newOptions)) {
                foreach ($newOptions as $option) {
                    \App\Models\ReservationOption::create([
                        'reservation_id' => $reservation->id,
                        'menu_option_id' => $option->id,
                        'quantity' => 1,
                        'price' => $option->price ?? 0,
                        'duration_minutes' => $option->duration_minutes ?? 0
                    ]);
                }
            }

            \DB::commit();

            // 変更通知イベントを発火
            $oldReservation = $reservation->replicate();
            $oldReservation->menu_id = $oldMenu->id;
            event(new ReservationChanged($oldReservation, $reservation->fresh()));

            return response()->json([
                'success' => true,
                'message' => 'メニューを変更しました',
                'data' => $reservation->fresh()->load(['menu', 'store', 'reservationOptions.menuOption']),
                'details' => [
                    'total_duration' => $totalDuration . '分',
                    'menu_duration' => $menuDuration . '分',
                    'options_duration' => $optionsDuration . '分',
                    'new_end_time' => $newEndTime->format('H:i')
                ]
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Menu change error', [
                'reservation_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'メニュー変更中にエラーが発生しました: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 店舗の利用可能なメニュー一覧を取得
     */
    public function getAvailableMenus(Request $request, $storeId)
    {
        $menus = \App\Models\Menu::where('store_id', $storeId)
            ->where('is_available', true)
            ->with('menuCategory')
            ->orderBy('category_id')
            ->orderBy('name')
            ->get()
            ->map(function ($menu) {
                return [
                    'id' => $menu->id,
                    'name' => $menu->name,
                    'price' => $menu->price,
                    'duration_minutes' => $menu->duration_minutes,
                    'category_name' => $menu->menuCategory->name ?? '未分類'
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $menus
        ]);
    }

    /**
     * 店舗の利用可能なオプションメニュー一覧を取得
     */
    public function getAvailableOptions(Request $request, $storeId)
    {
        $options = \App\Models\MenuOption::where('store_id', $storeId)
            ->where('is_available', true)
            ->orderBy('name')
            ->get()
            ->map(function ($option) {
                return [
                    'id' => $option->id,
                    'name' => $option->name,
                    'price' => $option->price ?? 0,
                    'duration_minutes' => $option->duration_minutes ?? 0
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $options
        ]);
    }

    /**
     * 予約番号から店舗情報を取得（LIFF連携用）
     */
    public function getStoreInfoByReservationNumber($reservationNumber)
    {
        try {
            $reservation = Reservation::where('reservation_number', $reservationNumber)
                ->with(['store', 'customer', 'menu'])
                ->first();

            if (!$reservation) {
                return response()->json([
                    'success' => false,
                    'error' => '予約が見つかりません'
                ], 404);
            }

            $store = $reservation->store;
            if (!$store->line_enabled || !$store->line_liff_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'LINE連携が有効でない店舗です'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'liff_id' => $store->line_liff_id,
                'store_name' => $store->name,
                'reservation' => [
                    'number' => $reservation->reservation_number,
                    'date' => $reservation->reservation_date,
                    'start_time' => $reservation->start_time,
                    'end_time' => $reservation->end_time,
                    'menu_name' => $reservation->menu->name ?? null,
                    'customer_name' => $reservation->customer->full_name,
                    'total_amount' => $reservation->total_amount
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Store info retrieval error', [
                'reservation_number' => $reservationNumber,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'エラーが発生しました'
            ], 500);
        }
    }
}
