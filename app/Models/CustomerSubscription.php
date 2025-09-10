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
        // 決済失敗管理
        'payment_failed',
        'payment_failed_at',
        'payment_failed_reason',
        'payment_failed_notes',
        // 休止管理
        'is_paused',
        'pause_start_date',
        'pause_end_date',
        'paused_by',
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
        // 決済失敗管理
        'payment_failed' => 'boolean',
        'payment_failed_at' => 'datetime',
        // 休止管理
        'is_paused' => 'boolean',
        'pause_start_date' => 'date',
        'pause_end_date' => 'date',
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
                    // メニューから契約期間を取得
                    if (!$subscription->contract_months && $menu->contract_months) {
                        $subscription->contract_months = $menu->contract_months;
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
            
            // 契約終了日の自動計算（サービス開始日 + 契約期間）
            if ($subscription->service_start_date && !$subscription->end_date) {
                // 契約期間の優先順位：
                // 1. メニューに設定された契約期間（最優先）
                // 2. サブスクリプション自体のcontract_months
                // 3. 最終手段として12ヶ月
                $contractMonths = null;
                
                // 1. メニューから契約期間を取得
                if ($subscription->menu_id) {
                    $menu = $menu ?? \App\Models\Menu::find($subscription->menu_id);
                    $contractMonths = $menu ? $menu->contract_months : null;
                }
                
                // 2. メニューになければサブスクリプション自体の設定を使用
                $contractMonths = $contractMonths ?? $subscription->contract_months;
                
                // 3. 最終的なデフォルト
                $contractMonths = $contractMonths ?? 12;
                
                $subscription->end_date = Carbon::parse($subscription->service_start_date)
                    ->addMonths($contractMonths)
                    ->format('Y-m-d');
            }
        });
        
        static::updating(function ($subscription) {
            // サービス開始日または契約期間が変更された場合、契約終了日を再計算
            if ($subscription->isDirty(['service_start_date', 'contract_months'])) {
                if ($subscription->service_start_date) {
                    $contractMonths = $subscription->contract_months ?? 12;
                    $subscription->end_date = Carbon::parse($subscription->service_start_date)
                        ->addMonths($contractMonths)
                        ->format('Y-m-d');
                }
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

    /**
     * 休止を実行したユーザー
     */
    public function pausedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paused_by');
    }

    /**
     * 休止履歴
     */
    public function pauseHistories()
    {
        return $this->hasMany(SubscriptionPauseHistory::class);
    }

    /**
     * 決済失敗の理由選択肢
     */
    public static function getPaymentFailedReasonOptions(): array
    {
        return [
            'card_expired' => 'カード期限切れ',
            'limit_exceeded' => '限度額超過',
            'insufficient' => '残高不足',
            'card_error' => 'カードエラー',
            'other' => 'その他',
        ];
    }

    /**
     * 決済失敗理由の表示名
     */
    public function getPaymentFailedReasonDisplayAttribute(): ?string
    {
        if (!$this->payment_failed_reason) {
            return null;
        }

        $reasons = static::getPaymentFailedReasonOptions();
        return $reasons[$this->payment_failed_reason] ?? $this->payment_failed_reason;
    }

    /**
     * 休止実行（6ヶ月間）
     */
    public function pause(int $pausedBy, ?string $notes = null): void
    {
        $startDate = now()->startOfDay();
        $endDate = $startDate->copy()->addMonths(6);

        // 既存の予約をキャンセル
        $cancelledCount = $this->cancelFutureReservations();

        // 休止状態に設定
        $this->update([
            'is_paused' => true,
            'pause_start_date' => $startDate,
            'pause_end_date' => $endDate,
            'paused_by' => $pausedBy,
        ]);

        // 履歴を記録
        $this->pauseHistories()->create([
            'pause_start_date' => $startDate,
            'pause_end_date' => $endDate,
            'paused_by' => $pausedBy,
            'paused_at' => now(),
            'cancelled_reservations_count' => $cancelledCount,
            'notes' => $notes,
        ]);
    }

    /**
     * 休止解除
     */
    public function resume(string $resumeType = 'manual'): void
    {
        $this->update([
            'is_paused' => false,
            'pause_start_date' => null,
            'pause_end_date' => null,
            'paused_by' => null,
        ]);

        // 最新の履歴を更新
        $latestHistory = $this->pauseHistories()
            ->whereNull('resumed_at')
            ->latest('paused_at')
            ->first();

        if ($latestHistory) {
            $latestHistory->update([
                'resumed_at' => now(),
                'resume_type' => $resumeType,
            ]);
        }
    }

    /**
     * 将来の予約をキャンセル
     */
    private function cancelFutureReservations(): int
    {
        $count = 0;
        
        // 今日以降の予約を取得
        $reservations = \App\Models\Reservation::where('customer_id', $this->customer_id)
            ->whereDate('reservation_date', '>=', now())
            ->whereIn('status', ['booked', 'confirmed'])
            ->get();

        foreach ($reservations as $reservation) {
            $reservation->update([
                'status' => 'cancelled',
                'cancel_reason' => 'サブスク休止のため自動キャンセル',
                'cancelled_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * 契約終了が迫っているか（30日以内）
     */
    public function isEndingSoon(): bool
    {
        return $this->end_date && 
               $this->end_date->diffInDays(now()) <= 30 &&
               $this->end_date->isAfter(now());
    }

    /**
     * 要対応かどうか
     */
    public function needsAttention(): bool
    {
        return $this->payment_failed || $this->isEndingSoon();
    }
}