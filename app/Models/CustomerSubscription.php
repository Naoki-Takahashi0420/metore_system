<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CustomerSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'store_id',
        'plan_id',
        'menu_id',
        'plan_type',
        'plan_name',
        'monthly_limit',
        'monthly_price',
        'billing_date',
        'billing_start_date',
        'service_start_date',
        'start_date',
        'contract_months',
        'end_date',
        'next_billing_date',
        'payment_method',
        'payment_reference',
        'current_month_visits',
        'last_visit_date',
        'reset_day',
        'status',
        'notes',
    ];

    protected $casts = [
        'monthly_limit' => 'integer',
        'monthly_price' => 'integer',
        'billing_date' => 'date',
        'billing_start_date' => 'date',
        'service_start_date' => 'date',
        'start_date' => 'date',
        'contract_months' => 'integer',
        'end_date' => 'date',
        'next_billing_date' => 'date',
        'last_visit_date' => 'date',
        'current_month_visits' => 'integer',
        'reset_day' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($subscription) {
            // menu_idが設定されている場合、メニュー情報から自動設定
            if ($subscription->menu_id) {
                $menu = \App\Models\Menu::find($subscription->menu_id);
                if ($menu) {
                    if (!$subscription->plan_name) {
                        $subscription->plan_name = $menu->name;
                    }
                    if (!$subscription->plan_type) {
                        $subscription->plan_type = 'MENU_' . $menu->id;
                    }
                }
            }
            
            // plan_typeとplan_nameが空の場合のデフォルト値
            if (!$subscription->plan_type) {
                $subscription->plan_type = 'CUSTOM';
            }
            if (!$subscription->plan_name) {
                $subscription->plan_name = 'カスタムプラン';
            }
        });
    }
    
    /**
     * 顧客
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * 店舗
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * メニュー
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * サブスクリプションプラン
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * スコープ: アクティブなサブスクリプション
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * スコープ: 期限切れ間近（7日以内）
     */
    public function scopeExpiringSoon($query)
    {
        return $query->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays(7)]);
    }

    /**
     * サブスクリプションが有効かチェック
     */
    public function isActive(): bool
    {
        // service_start_dateがあればそれを使用、なければstart_dateを使用
        $startDate = $this->service_start_date ?? $this->start_date;
        
        return $this->status === 'active' 
            && $startDate <= now()
            && ($this->end_date === null || $this->end_date >= now());
    }

    /**
     * 今月の残り回数を取得
     */
    public function getRemainingVisitsAttribute(): ?int
    {
        if ($this->monthly_limit === null) {
            return null; // 無制限
        }

        return max(0, $this->monthly_limit - $this->current_month_visits);
    }

    /**
     * 回数制限に達しているかチェック
     */
    public function hasReachedLimit(): bool
    {
        if ($this->monthly_limit === null) {
            return false; // 無制限
        }

        return $this->current_month_visits >= $this->monthly_limit;
    }

    /**
     * 来店を記録
     */
    public function recordVisit(): void
    {
        $this->increment('current_month_visits');
        $this->update(['last_visit_date' => now()]);
    }

    /**
     * 月次リセット処理
     */
    public function resetMonthlyCount(): void
    {
        $this->update([
            'current_month_visits' => 0,
            'next_billing_date' => Carbon::now()->addMonth()->day($this->reset_day),
        ]);
    }

    /**
     * プラン名と制限を表示用に整形
     */
    public function getDisplayNameAttribute(): string
    {
        $limit = $this->monthly_limit ? "月{$this->monthly_limit}回" : '無制限';
        return "{$this->plan_name} ({$limit})";
    }

    /**
     * 支払い方法の表示名
     */
    public function getPaymentMethodDisplayAttribute(): string
    {
        $methods = [
            'robopay' => 'ロボットペイメント',
            'credit' => 'クレジットカード',
            'bank' => '銀行振込',
            'cash' => '現金',
        ];

        return $methods[$this->payment_method] ?? $this->payment_method;
    }
}