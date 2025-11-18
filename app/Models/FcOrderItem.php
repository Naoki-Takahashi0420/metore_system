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
}
