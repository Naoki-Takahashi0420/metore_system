<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketUsageHistory extends Model
{
    use HasFactory;

    protected $table = 'ticket_usage_history';

    protected $fillable = [
        'customer_ticket_id',
        'reservation_id',
        'used_at',
        'used_count',
        'is_cancelled',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'notes',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'used_count' => 'integer',
        'is_cancelled' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    /**
     * 顧客回数券
     */
    public function customerTicket(): BelongsTo
    {
        return $this->belongsTo(CustomerTicket::class);
    }

    /**
     * 予約
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * 取り消したユーザー
     */
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * スコープ: 取り消されていない履歴のみ
     */
    public function scopeActive($query)
    {
        return $query->where('is_cancelled', false);
    }

    /**
     * スコープ: 取り消された履歴のみ
     */
    public function scopeCancelled($query)
    {
        return $query->where('is_cancelled', true);
    }

    /**
     * この履歴をキャンセル
     */
    public function cancel(int $cancelledBy, ?string $reason = null): void
    {
        $this->update([
            'is_cancelled' => true,
            'cancelled_at' => now(),
            'cancelled_by' => $cancelledBy,
            'cancel_reason' => $reason,
        ]);
    }

    /**
     * 表示用のステータス
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->is_cancelled ? '取消済み' : '利用済み';
    }
}
