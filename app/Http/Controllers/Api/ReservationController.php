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
        //
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

        return response()->json([
            'success' => true,
            'message' => '予約を完了しました',
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
            'is_subscription' => 'boolean'
        ]);
        
        // メニュー情報取得
        $menu = \App\Models\Menu::find($validated['menu_id']);
        
        // 予約番号生成
        $reservationNumber = 'R' . date('YmdHis') . rand(100, 999);
        
        // 終了時間計算
        $startTime = \Carbon\Carbon::parse($validated['reservation_date'] . ' ' . $validated['start_time']);
        $endTime = $startTime->copy()->addMinutes($menu->duration_minutes ?? 60);
        
        // 予約作成（既存システムと同じフィールド形式）
        $reservation = Reservation::create([
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
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '予約が完了しました',
            'data' => $reservation->load(['store', 'menu'])
        ], 201);
    }

    /**
     * 顧客の予約履歴取得
     */
    public function customerReservations(Request $request)
    {
        $customer = $request->user();
        
        $reservations = Reservation::where('customer_id', $customer->id)
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

        return response()->json([
            'message' => '予約詳細を取得しました',
            'data' => $reservation
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

        // 変更を保存
        $oldDate = $reservation->reservation_date;
        $oldTime = $reservation->start_time;
        
        $reservation->update([
            'reservation_date' => $validated['new_date'],
            'start_time' => $validated['new_time'],
            'end_time' => $newEndTime->format('H:i:s')
        ]);

        // 顧客の変更回数を更新
        $customer->increment('change_count');
        
        // 変更通知を送信
        event(new ReservationChanged($reservation, [
            'old_date' => $oldDate,
            'old_time' => $oldTime,
            'new_date' => $validated['new_date'],
            'new_time' => $validated['new_time']
        ]));

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
