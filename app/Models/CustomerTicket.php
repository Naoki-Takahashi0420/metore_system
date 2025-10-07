<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class CustomerTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'store_id',
        'ticket_plan_id',
        'plan_name',
        'total_count',
        'used_count',
        'purchase_price',
        'purchased_at',
        'expires_at',
        'expiry_notified_at',
        'status',
        'payment_method',
        'payment_reference',
        'notes',
        'sold_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'total_count' => 'integer',
        'used_count' => 'integer',
        'purchase_price' => 'integer',
        'purchased_at' => 'datetime',
        'expires_at' => 'datetime',
        'expiry_notified_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $appends = ['remaining_count', 'is_expired', 'is_expiring_soon', 'days_until_expiry'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            // 購入日時が未設定なら現在時刻を設定
            if (!$ticket->purchased_at) {
                $ticket->purchased_at = now();
            }

            // プランから有効期限を計算
            if ($ticket->ticket_plan_id && !$ticket->expires_at) {
                $plan = TicketPlan::find($ticket->ticket_plan_id);
                if ($plan) {
                    $ticket->expires_at = $ticket->calculateExpiryDate($plan);
                }
            }
        });

        static::updating(function ($ticket) {
            // 使い切ったらステータスを変更
            if ($ticket->used_count >= $ticket->total_count && $ticket->status === 'active') {
                $ticket->status = 'used_up';
            }

            // 有効期限が過ぎたらステータスを変更
            if ($ticket->expires_at && $ticket->expires_at->isPast() && $ticket->status === 'active') {
                $ticket->status = 'expired';
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
     * 回数券プラン
     */
    public function ticketPlan(): BelongsTo
    {
        return $this->belongsTo(TicketPlan::class);
    }

    /**
     * 販売したスタッフ
     */
    public function soldByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    /**
     * キャンセルしたユーザー
     */
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * 利用履歴
     */
    public function usageHistory(): HasMany
    {
        return $this->hasMany(TicketUsageHistory::class);
    }

    /**
     * この回数券を使用した予約
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'customer_ticket_id');
    }

    /**
     * スコープ: アクティブな回数券のみ
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->where(function ($q) {
                         $q->whereNull('expires_at')
                           ->orWhere('expires_at', '>', now());
                     })
                     ->whereColumn('used_count', '<', 'total_count');
    }

    /**
     * スコープ: 有効期限切れ間近（7日以内）
     */
    public function scopeExpiringSoon($query)
    {
        return $query->where('status', 'active')
                     ->whereNotNull('expires_at')
                     ->whereBetween('expires_at', [now(), now()->addDays(7)]);
    }

    /**
     * スコープ: 特定顧客の回数券
     */
    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * スコープ: 特定店舗の回数券
     */
    public function scopeForStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * 残回数を取得（計算プロパティ）
     */
    public function getRemainingCountAttribute(): int
    {
        return max(0, $this->total_count - $this->used_count);
    }

    /**
     * 有効期限切れかどうか
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * 有効期限が近いかどうか（7日以内）
     */
    public function getIsExpiringSoonAttribute(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->isFuture() &&
               now()->diffInDays($this->expires_at) <= 7;
    }

    /**
     * 有効期限までの日数
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return (int) now()->diffInDays($this->expires_at, false);
    }

    /**
     * 使用可能かどうか
     */
    public function canUse(): bool
    {
        return $this->status === 'active' &&
               $this->remaining_count > 0 &&
               !$this->is_expired;
    }

    /**
     * 回数券を使用する
     */
    public function use(?int $reservationId = null, int $count = 1): bool
    {
        if (!$this->canUse() || $this->remaining_count < $count) {
            return false;
        }

        // 利用回数を増やす
        $this->increment('used_count', $count);

        // 利用履歴を記録
        $this->usageHistory()->create([
            'reservation_id' => $reservationId,
            'used_at' => now(),
            'used_count' => $count,
        ]);

        // 使い切ったらステータス更新
        if ($this->fresh()->used_count >= $this->total_count) {
            $this->update(['status' => 'used_up']);
        }

        return true;
    }

    /**
     * 回数券の使用を取り消す（予約キャンセル時）
     */
    public function refund(?int $reservationId = null, int $count = 1): bool
    {
        if ($this->used_count < $count) {
            return false;
        }

        // 利用回数を減らす
        $this->decrement('used_count', $count);

        // 該当する利用履歴を取り消し
        if ($reservationId) {
            $history = $this->usageHistory()
                ->where('reservation_id', $reservationId)
                ->where('is_cancelled', false)
                ->first();

            if ($history) {
                $history->update([
                    'is_cancelled' => true,
                    'cancelled_at' => now(),
                    'cancel_reason' => '予約キャンセルによる返却',
                ]);
            }
        }

        // 使い切り状態だったらアクティブに戻す
        if ($this->status === 'used_up' && $this->fresh()->used_count < $this->total_count) {
            $this->update(['status' => 'active']);
        }

        return true;
    }

    /**
     * 回数券をキャンセル
     */
    public function cancel(int $cancelledBy, ?string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $cancelledBy,
            'cancel_reason' => $reason,
        ]);
    }

    /**
     * 有効期限を計算
     */
    private function calculateExpiryDate(TicketPlan $plan): ?Carbon
    {
        $purchaseDate = $this->purchased_at ?? now();

        if (!$plan->validity_months && !$plan->validity_days) {
            return null; // 無期限
        }

        $expiryDate = Carbon::parse($purchaseDate);

        if ($plan->validity_months) {
            $expiryDate->addMonths($plan->validity_months);
        }

        if ($plan->validity_days) {
            $expiryDate->addDays($plan->validity_days);
        }

        return $expiryDate->endOfDay();
    }

    /**
     * 有効期限切れにする
     */
    public function markAsExpired(): void
    {
        if ($this->status === 'active' && $this->is_expired) {
            $this->update(['status' => 'expired']);
        }
    }

    /**
     * 表示用のステータスラベル
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'active' => $this->is_expired ? '期限切れ' : ($this->remaining_count > 0 ? '利用可能' : '使い切り'),
            'expired' => '期限切れ',
            'used_up' => '使い切り',
            'cancelled' => 'キャンセル',
            default => $this->status,
        };
    }

    /**
     * 表示用の進捗率（パーセント）
     */
    public function getProgressPercentAttribute(): int
    {
        if ($this->total_count <= 0) {
            return 0;
        }

        return (int) (($this->used_count / $this->total_count) * 100);
    }
}
