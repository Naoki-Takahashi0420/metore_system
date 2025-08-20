<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'category',
        'name',
        'description',
        'price',
        'duration',
        'is_available',
        'max_daily_quantity',
        'sort_order',
        'options',
        'tags',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration' => 'integer',
        'is_available' => 'boolean',
        'max_daily_quantity' => 'integer',
        'sort_order' => 'integer',
        'options' => 'array',
        'tags' => 'array',
    ];

    /**
     * リレーション: 店舗
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * スコープ: 利用可能なメニュー
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    /**
     * スコープ: カテゴリ別
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * スコープ: ソート順
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}