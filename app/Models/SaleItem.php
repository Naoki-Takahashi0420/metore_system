<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'menu_id',
        'item_type',
        'item_name',
        'item_description',
        'unit_price',
        'quantity',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'amount',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    /**
     * リレーション：売上
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * リレーション：メニュー
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * 金額を計算
     */
    public function calculateAmount(): void
    {
        $subtotal = $this->unit_price * $this->quantity - $this->discount_amount;
        $this->tax_amount = round($subtotal * ($this->tax_rate / 100), 2);
        $this->amount = $subtotal + $this->tax_amount;
    }
}