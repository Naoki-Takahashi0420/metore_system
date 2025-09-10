<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPauseHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_subscription_id',
        'pause_start_date',
        'pause_end_date',
        'paused_by',
        'paused_at',
        'resumed_at',
        'resume_type',
        'cancelled_reservations_count',
        'notes',
    ];

    protected $casts = [
        'pause_start_date' => 'date',
        'pause_end_date' => 'date',
        'paused_at' => 'datetime',
        'resumed_at' => 'datetime',
        'cancelled_reservations_count' => 'integer',
    ];

    /**
     * 関連するサブスクリプション
     */
    public function customerSubscription(): BelongsTo
    {
        return $this->belongsTo(CustomerSubscription::class);
    }

    /**
     * 休止を実行したユーザー
     */
    public function pausedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paused_by');
    }

    /**
     * 休止中かどうか
     */
    public function isActive(): bool
    {
        return is_null($this->resumed_at) && 
               now()->between($this->pause_start_date, $this->pause_end_date);
    }

    /**
     * 自動再開予定かどうか
     */
    public function isPendingAutoResume(): bool
    {
        return is_null($this->resumed_at) && 
               now()->greaterThan($this->pause_end_date);
    }
}