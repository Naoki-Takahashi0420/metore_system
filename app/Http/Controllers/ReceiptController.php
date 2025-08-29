<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Reservation;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    /**
     * レシート印刷画面
     */
    public function print(Request $request, $id)
    {
        $sale = Sale::with(['customer', 'store', 'staff', 'reservation'])->findOrFail($id);
        
        // 販売アイテムの詳細を構築
        $items = $this->buildItemsList($sale);
        
        // 支払い方法のラベル
        $paymentMethodLabels = [
            'cash' => '現金',
            'credit_card' => 'クレジットカード',
            'debit_card' => 'デビットカード',
            'e_money' => '電子マネー',
            'qr_payment' => 'QRコード決済',
            'bank_transfer' => '銀行振込',
            'other' => 'その他',
        ];
        
        return view('receipts.print', [
            'sale' => $sale,
            'customer' => $sale->customer,
            'store' => $sale->store,
            'staff' => $sale->staff,
            'items' => $items,
            'paymentMethodLabels' => $paymentMethodLabels,
        ]);
    }
    
    /**
     * 予約からレシート印刷
     */
    public function printFromReservation(Request $request, $reservationId)
    {
        $reservation = Reservation::with(['customer', 'store', 'staff', 'menu', 'reservationOptions.menuOption'])->findOrFail($reservationId);
        
        // 仮の売上データを作成（実際の売上がない場合）
        $sale = new Sale([
            'receipt_number' => 'R' . str_pad($reservation->id, 8, '0', STR_PAD_LEFT),
            'sale_date' => $reservation->reservation_date,
            'customer_id' => $reservation->customer_id,
            'store_id' => $reservation->store_id,
            'staff_id' => $reservation->staff_id,
            'subtotal' => $reservation->total_amount,
            'tax_rate' => 10,
            'tax_amount' => (int)($reservation->total_amount * 0.1 / 1.1),
            'discount_amount' => 0,
            'total_amount' => $reservation->total_amount,
            'payment_method' => $reservation->payment_method ?? 'cash',
            'status' => 'completed',
        ]);
        
        // アイテムリストを構築
        $items = [];
        
        // メインメニュー
        $items[] = [
            'name' => $reservation->menu->name,
            'quantity' => 1,
            'price' => $reservation->menu->price,
            'options' => [],
        ];
        
        // オプション
        foreach ($reservation->reservationOptions as $option) {
            $items[0]['options'][] = [
                'name' => $option->menuOption->name,
                'quantity' => $option->quantity,
                'price' => $option->price,
            ];
        }
        
        $paymentMethodLabels = [
            'cash' => '現金',
            'credit_card' => 'クレジットカード',
            'debit_card' => 'デビットカード',
            'e_money' => '電子マネー',
            'qr_payment' => 'QRコード決済',
            'bank_transfer' => '銀行振込',
            'other' => 'その他',
        ];
        
        return view('receipts.print', [
            'sale' => $sale,
            'customer' => $reservation->customer,
            'store' => $reservation->store,
            'staff' => $reservation->staff,
            'items' => $items,
            'paymentMethodLabels' => $paymentMethodLabels,
        ]);
    }
    
    /**
     * 販売アイテムのリストを構築
     */
    private function buildItemsList(Sale $sale): array
    {
        $items = [];
        
        if ($sale->reservation) {
            // 予約からのアイテム
            $items[] = [
                'name' => $sale->reservation->menu->name,
                'quantity' => 1,
                'price' => $sale->reservation->menu->price,
                'options' => $sale->reservation->reservationOptions->map(function ($option) {
                    return [
                        'name' => $option->menuOption->name,
                        'quantity' => $option->quantity,
                        'price' => $option->price,
                    ];
                })->toArray(),
            ];
        } else {
            // 売上詳細からのアイテム（将来の拡張用）
            // $items = $sale->items->map(...)->toArray();
        }
        
        return $items;
    }
}