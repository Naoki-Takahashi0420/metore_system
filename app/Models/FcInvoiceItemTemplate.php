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
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
