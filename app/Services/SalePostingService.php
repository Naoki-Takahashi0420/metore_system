<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentSource;
use App\Models\Reservation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\CustomerTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 売上計上の統一サービス
 *
 * 全ての売上計上はこのサービスを経由して行う。
 * これにより、二重計上防止、監査ログ、統一ロジックを保証する。
 */
class SalePostingService
{
    /**
     * 売上を計上する
     *
     * @param Reservation $reservation 予約
     * @param string|null $paymentMethod 支払方法（nullの場合は自動判定）
     * @param array $options オプションメニュー [{menu_option_id, quantity, price}, ...]
     * @param array $products 物販 [{name, quantity, price, tax_rate}, ...]
     * @return Sale 作成された売上
     * @throws \Exception 二重計上やバリデーションエラー
     */
    public function post(
        Reservation $reservation,
        ?string $paymentMethod = null,
        array $options = [],
        array $products = [],
        int $discountAmount = 0
    ): Sale {
        DB::beginTransaction();

        try {
            // 既に売上が計上されているかチェック
            if ($reservation->sale()->exists()) {
                throw new \Exception("この予約は既に売上計上されています。（Reservation ID: {$reservation->id}）");
            }

            // 支払方法の決定
            $paymentMethod = $this->determinePaymentMethod($reservation, $paymentMethod);

            // 支払ソースの判定（subscription/ticket/spot）
            $paymentSource = $this->determinePaymentSource($reservation);

            // 金額計算
            $amounts = $this->calculateAmounts($reservation, $paymentSource, $options, $products, $discountAmount);

            // バリデーション: 金額>0の場合は支払方法必須
            if ($amounts['total_amount'] > 0 && empty($paymentMethod)) {
                throw new \Exception('金額が発生する場合は支払方法を選択してください。（現金・クレジットカード等）');
            }

            // 売上レコード作成
            // スタッフID: カルテの対応者 → 予約のスタッフ → ログインユーザー
            $staffId = $reservation->medicalRecords()->latest()->first()?->staff_id
                ?? $reservation->staff_id
                ?? auth()->id();

            $saleData = [
                'sale_number' => Sale::generateSaleNumber(),
                'store_id' => $reservation->store_id,
                'reservation_id' => $reservation->id,
                'customer_id' => $reservation->customer_id,
                'staff_id' => $staffId,
                'customer_ticket_id' => $paymentSource === PaymentSource::TICKET->value ? $reservation->customer_ticket_id : null,
                'customer_subscription_id' => $paymentSource === PaymentSource::SUBSCRIPTION->value ? $reservation->customer_subscription_id : null,
                'payment_method' => $paymentMethod,
                'payment_source' => $paymentSource,
                'sale_date' => $reservation->reservation_date ?? now()->toDateString(),
                'sale_time' => now()->format('H:i'),
                'subtotal' => $amounts['subtotal'],
                'tax_amount' => $amounts['tax_amount'],
                'discount_amount' => $discountAmount,
                'total_amount' => $amounts['total_amount'],
                'status' => 'completed',
                'completed_at' => now(),
                'notes' => "予約番号: {$reservation->reservation_number}",
            ];

            // 支払ソースに応じた追加情報
            if ($paymentSource === PaymentSource::SUBSCRIPTION->value && $reservation->customer_subscription_id) {
                $saleData['notes'] .= " | サブスク利用";
            } elseif ($paymentSource === PaymentSource::TICKET->value && $reservation->customer_ticket_id) {
                $ticket = CustomerTicket::find($reservation->customer_ticket_id);
                if ($ticket) {
                    $remaining = $ticket->remaining_count;
                    $saleData['notes'] .= " | 回数券利用 (残り: {$remaining}回)";
                }
            }

            $sale = Sale::create($saleData);

            // メニュー明細を作成（スポットのみ）
            if ($paymentSource === PaymentSource::SPOT->value && $reservation->menu) {
                $sale->items()->create([
                    'menu_id' => $reservation->menu_id,
                    'item_type' => 'service',
                    'item_name' => $reservation->menu->name,
                    'item_description' => $reservation->menu->description,
                    'unit_price' => $reservation->menu->price ?? 0,
                    'quantity' => 1,
                    'discount_amount' => 0,
                    'tax_rate' => 0.1,
                    'tax_amount' => floor(($reservation->menu->price ?? 0) * 0.1),
                    'amount' => $reservation->menu->price ?? 0,
                ]);
            }

            // 売上明細の作成（オプション）
            foreach ($options as $option) {
                $this->createSaleItem($sale, 'option', $option);
            }

            // 売上明細の作成（物販）
            foreach ($products as $product) {
                $this->createSaleItem($sale, 'product', $product);
            }

            // 回数券の場合は使用履歴を記録
            if ($paymentSource === PaymentSource::TICKET->value && $reservation->customer_ticket_id) {
                $ticket = CustomerTicket::findOrFail($reservation->customer_ticket_id);

                // 残数チェック
                if ($ticket->remaining_count < 1) {
                    throw new \Exception("回数券の残数が不足しています。（残り: {$ticket->remaining_count}回）");
                }

                // 有効期限チェック
                if ($ticket->expires_at && $ticket->expires_at->isPast()) {
                    throw new \Exception("回数券の有効期限が切れています。（有効期限: {$ticket->expires_at->format('Y/m/d')}）");
                }

                // 使用履歴を記録
                $used = $ticket->use($reservation->id, 1); // 1回分消費
                if (!$used) {
                    throw new \Exception("回数券の使用処理に失敗しました。");
                }
            }

            // 監査ログ
            Log::info("売上計上完了", [
                'sale_id' => $sale->id,
                'reservation_id' => $reservation->id,
                'payment_source' => $paymentSource,
                'payment_method' => $paymentMethod,
                'total_amount' => $amounts['total_amount'],
                'user_id' => auth()->id(),
            ]);

            DB::commit();

            return $sale;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("売上計上エラー", [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * 売上を取り消す（void処理）
     *
     * @param Sale $sale 取り消す売上
     * @return bool 成功した場合true
     * @throws \Exception 取り消し処理エラー
     */
    public function void(Sale $sale): bool
    {
        DB::beginTransaction();

        try {
            // 回数券の場合は使用履歴を返金
            if ($sale->customer_ticket_id && $sale->reservation_id) {
                $ticket = CustomerTicket::find($sale->customer_ticket_id);
                if ($ticket) {
                    $ticket->refund($sale->reservation_id, 1); // 1回分返金
                }
            }

            // ポイント履歴を削除（sale_idで紐づくもの）
            DB::table('point_transactions')->where('sale_id', $sale->id)->delete();

            // 売上明細を削除
            $sale->items()->delete();

            // 売上を削除（ソフトデリート）
            $sale->delete();

            // 監査ログ
            Log::info("売上取り消し完了", [
                'sale_id' => $sale->id,
                'reservation_id' => $sale->reservation_id,
                'voided_by' => auth()->id(),
            ]);

            DB::commit();

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("売上取り消しエラー", [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 支払方法を決定する
     */
    protected function determinePaymentMethod(Reservation $reservation, ?string $paymentMethod): string
    {
        // 指定されている場合はそれを使用
        if ($paymentMethod) {
            return $paymentMethod;
        }

        // カルテから取得を試みる
        $medicalRecord = $reservation->medicalRecord;
        if ($medicalRecord && $medicalRecord->payment_method) {
            return $medicalRecord->payment_method;
        }

        // デフォルト値を取得
        $paymentSource = $this->determinePaymentSource($reservation);

        return match ($paymentSource) {
            PaymentSource::SPOT->value => config('payments.defaults.payment_method_spot', PaymentMethod::CASH->value),
            PaymentSource::SUBSCRIPTION->value => config('payments.defaults.payment_method_subscription', PaymentMethod::OTHER->value),
            PaymentSource::TICKET->value => config('payments.defaults.payment_method_ticket', PaymentMethod::OTHER->value),
            default => PaymentMethod::CASH->value,
        };
    }

    /**
     * 支払ソースを判定する（subscription/ticket/spot）
     */
    protected function determinePaymentSource(Reservation $reservation): string
    {
        // サブスクリプション判定（正しいフィールド名: customer_subscription_id）
        if ($reservation->customer_subscription_id) {
            return PaymentSource::SUBSCRIPTION->value;
        }

        // 回数券判定
        if ($reservation->customer_ticket_id) {
            return PaymentSource::TICKET->value;
        }

        // それ以外はスポット
        return PaymentSource::SPOT->value;
    }

    /**
     * 金額を計算する
     *
     * ルール:
     * - subscription/ticket: 基本料金0円、オプション/物販のみ課金
     * - spot: 基本料金 + オプション/物販
     */
    protected function calculateAmounts(
        Reservation $reservation,
        string $paymentSource,
        array $options,
        array $products,
        int $discountAmount = 0
    ): array {
        $subtotal = 0;

        // スポット予約の場合のみ基本料金を加算
        if ($paymentSource === PaymentSource::SPOT->value) {
            $menuPrice = $reservation->menu?->price ?? 0;
            $subtotal += $menuPrice;
        }

        // オプション料金を加算
        foreach ($options as $option) {
            $optionTotal = ($option['price'] ?? 0) * ($option['quantity'] ?? 1);
            $subtotal += $optionTotal;
        }

        // 物販料金を加算
        foreach ($products as $product) {
            $productTotal = ($product['price'] ?? 0) * ($product['quantity'] ?? 1);
            $subtotal += $productTotal;
        }

        // 内税計算のため税額は0
        $taxAmount = 0;

        // 合計 = 小計 - 割引
        $totalAmount = $subtotal - $discountAmount;
        if ($totalAmount < 0) {
            $totalAmount = 0;
        }

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * 売上明細を作成する
     */
    protected function createSaleItem(Sale $sale, string $type, array $data): SaleItem
    {
        $quantity = $data['quantity'] ?? 1;
        $unitPrice = $data['price'] ?? 0;
        $taxRate = $data['tax_rate'] ?? 0.1;

        // 小計と税額を計算
        $amount = $unitPrice * $quantity;
        $taxAmount = floor($amount * $taxRate);

        $itemData = [
            'sale_id' => $sale->id,
            'type' => $type,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => $amount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
        ];

        // タイプに応じて追加フィールドを設定
        if ($type === 'option') {
            $itemData['menu_option_id'] = $data['menu_option_id'] ?? null;
            $itemData['item_name'] = $data['name'] ?? 'オプション';
        } elseif ($type === 'product') {
            $itemData['item_name'] = $data['name'] ?? '物販';
        } else {
            // メニュー/コースなどの場合
            $itemData['item_name'] = $data['name'] ?? 'メニュー';
        }

        return SaleItem::create($itemData);
    }
}
