<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FcInvoiceItemTemplate extends Model
{
    protected $fillable = [
        'name',
        'type',
        'description',
        'unit_price',
        'quantity',
        'tax_rate',
        'sort_order',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * デフォルトで追加するテンプレートを取得
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true)->where('is_active', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
