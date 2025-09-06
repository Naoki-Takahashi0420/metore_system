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
        'code',
        'description',
        'price',
        'max_reservations',
        'contract_months',
        'max_users',
        'notes',
        'is_active',
        'sort_order',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($plan) {
            if (empty($plan->code)) {
                $plan->code = 'PLAN_' . strtoupper(uniqid());
            }
        });
    }
    
    protected $casts = [
        'price' => 'integer',
        'max_reservations' => 'integer',
        'contract_months' => 'integer',
        'max_users' => 'integer',
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