<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
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
     * 顧客の予約履歴取得
     */
    public function customerReservations(Request $request)
    {
        $customer = $request->user();
        
        $reservations = Reservation::where('customer_id', $customer->id)
            ->with(['store', 'menu', 'staff'])
            ->orderBy('reservation_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();

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
        $reservationDateTime = \Carbon\Carbon::parse($reservation->reservation_date . ' ' . $reservation->start_time);
        $now = \Carbon\Carbon::now();
        
        if ($reservationDateTime->diffInHours($now) < 24) {
            return response()->json([
                'message' => '予約の24時間前を過ぎているため、キャンセルできません'
            ], 400);
        }

        // キャンセル実行
        $reservation->update([
            'status' => 'cancelled',
            'cancel_reason' => $request->input('cancel_reason', '顧客都合'),
            'cancelled_at' => now()
        ]);

        return response()->json([
            'message' => '予約をキャンセルしました',
            'data' => $reservation
        ]);
    }
}
