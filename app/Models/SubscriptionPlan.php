<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'duration_days',
        'features',
        'max_reservations',
        'discount_rate',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'integer',
        'duration_days' => 'integer',
        'features' => 'array',
        'max_reservations' => 'integer',
        'discount_rate' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * このプランのサブスクリプション
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(CustomerSubscription::class, 'plan_id');
    }

    /**
     * フォーマット済み価格
     */
    public function getFormattedPriceAttribute(): string
    {
        return '¥' . number_format($this->price) . '/月';
    }

    /**
     * 特典リスト取得
     */
    public function getFeatureListAttribute(): array
    {
        return $this->features ?? [];
    }

    /**
     * スコープ: アクティブなプラン
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * スコープ: 並び順でソート
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }

    /**
     * プランが削除可能かチェック
     */
    public function canBeDeleted(): bool
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->count() === 0;
    }

    /**
     * デフォルトプランかチェック
     */
    public function isDefault(): bool
    {
        return $this->sort_order === 0;
    }
}