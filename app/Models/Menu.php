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
        'image_path',
        'is_available',
        'is_option',
        'show_in_upsell',
        'upsell_description',
        'customer_type_restriction',
        'medical_record_only',
        'max_daily_quantity',
        'sort_order',
        'display_order',
        'options',
        'tags',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration' => 'integer',
        'is_available' => 'boolean',
        'is_option' => 'boolean',
        'show_in_upsell' => 'boolean',
        'medical_record_only' => 'boolean',
        'max_daily_quantity' => 'integer',
        'sort_order' => 'integer',
        'display_order' => 'integer',
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

    /**
     * スコープ: 顧客タイプによるフィルタリング
     */
    public function scopeForCustomerType($query, $isNewCustomer = true, $isFromMedicalRecord = false)
    {
        // カルテからの予約の場合
        if ($isFromMedicalRecord) {
            return $query->where(function ($q) {
                $q->whereIn('customer_type_restriction', ['all', 'existing'])
                  ->orWhere('medical_record_only', true);
            });
        }
        
        // 通常予約の場合（カルテ専用メニューは除外）
        $query->where('medical_record_only', false);
        
        // 新規顧客の場合
        if ($isNewCustomer) {
            return $query->whereIn('customer_type_restriction', ['all', 'new']);
        }
        
        // 既存顧客の場合（通常予約から）
        return $query->whereIn('customer_type_restriction', ['all', 'existing']);
    }
}