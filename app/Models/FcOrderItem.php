<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FcOrderItem extends Model
{
    protected $fillable = [
        'fc_order_id',
        'fc_product_id',
        'product_name',
        'product_sku',
        'quantity',
        'shipped_quantity',
        'shipping_status',
        'unit_price',
        'tax_rate',
        'subtotal',
        'tax_amount',
        'total',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * 発注
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(FcOrder::class, 'fc_order_id');
    }

    /**
     * 商品
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(FcProduct::class, 'fc_product_id');
    }

    /**
     * 金額を計算（商品と数量から自動計算）
     */
    public static function calculateAmounts(FcProduct $product, int $quantity): array
    {
        $unitPrice = floatval($product->unit_price);
        $taxRate = floatval($product->tax_rate);

        $subtotal = $unitPrice * $quantity;
        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;

        return [
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => $unitPrice,
            'tax_rate' => $taxRate,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ];
    }

    /**
     * 未発送数量を取得
     */
    public function getUnshippedQuantityAttribute(): int
    {
        return max(0, $this->quantity - ($this->shipped_quantity ?? 0));
    }

    /**
     * 発送済み数量に基づいて金額を計算
     */
    public function calculateShippedAmounts(): array
    {
        $shippedQty = $this->shipped_quantity ?? 0;
        $unitPrice = floatval($this->unit_price);
        $taxRate = floatval($this->tax_rate);

        $subtotal = $unitPrice * $shippedQty;
        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;

        return [
            'quantity' => $shippedQty,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ];
    }

    /**
     * 発送ステータスを更新
     */
    public function updateShippingStatus(): void
    {
        if ($this->shipped_quantity >= $this->quantity) {
            $this->shipping_status = 'completed';
        } elseif ($this->shipped_quantity > 0) {
            $this->shipping_status = 'partial';
        } else {
            $this->shipping_status = 'pending';
        }
        $this->save();
    }
}
