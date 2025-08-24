<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Reservation;
use Illuminate\Http\Request;

class CustomerCheckController extends Controller
{
    /**
     * 電話番号で既存顧客と未完了予約をチェック
     */
    public function checkPhone(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|regex:/^[0-9\-]+$/',
        ]);
        
        // ハイフンを除去して検索
        $phoneDigits = str_replace('-', '', $validated['phone']);
        
        // 既存顧客を検索
        $customer = Customer::where('phone', $phoneDigits)->first();
        
        if (!$customer) {
            return response()->json([
                'exists' => false,
                'message' => '新規のお客様です'
            ]);
        }
        
        // 今後の全ての予約を取得
        $futureReservations = Reservation::where('customer_id', $customer->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('reservation_date', '>=', today())
            ->orderBy('reservation_date')
            ->orderBy('start_time')
            ->with(['store', 'menu'])
            ->get();
            
        if ($futureReservations->isEmpty()) {
            return response()->json([
                'exists' => true,
                'has_pending_reservations' => false,
                'customer' => [
                    'last_name' => $customer->last_name,
                    'first_name' => $customer->first_name,
                    'last_name_kana' => $customer->last_name_kana,
                    'first_name_kana' => $customer->first_name_kana,
                    'email' => $customer->email,
                ],
                'message' => 'お客様情報が見つかりました'
            ]);
        }
        
        // 今後の予約がある場合は、予約変更を促す（2回目以降の顧客）
        return response()->json([
            'exists' => true,
            'has_pending_reservations' => true,
            'block_reservation' => true, // 既存顧客は新規予約不可
            'is_returning_customer' => true,
            'customer' => [
                'last_name' => $customer->last_name,
                'first_name' => $customer->first_name,
            ],
            'future_reservations' => $futureReservations->map(function ($reservation) {
                return [
                    'reservation_number' => $reservation->reservation_number,
                    'store_name' => $reservation->store->name,
                    'menu_name' => $reservation->menu->name,
                    'reservation_date' => $reservation->reservation_date,
                    'start_time' => $reservation->start_time,
                    'status' => $reservation->status,
                ];
            }),
            'message' => '既存のご予約があります。予約の変更・確認はマイページから行ってください。'
        ]);
    }
}