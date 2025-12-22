<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastMessage extends Model
{
    protected $fillable = [
        'store_id',
        'subject',
        'message',
        'status',
        'scheduled_at',
        'sent_at',
        'total_recipients',
        'line_count',
        'email_count',
        'success_count',
        'failed_count',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'total_recipients' => 'integer',
        'line_count' => 'integer',
        'email_count' => 'integer',
        'success_count' => 'integer',
        'failed_count' => 'integer',
    ];

    /**
     * ステータス定数
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_SENDING = 'sending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';

    /**
     * ステータスラベル
     */
    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_DRAFT => '下書き',
            self::STATUS_SCHEDULED => '予約済み',
            self::STATUS_SENDING => '送信中',
            self::STATUS_SENT => '送信完了',
            self::STATUS_FAILED => '送信失敗',
        ];
    }

    /**
     * 店舗
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * 作成者
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 送信可能かチェック
     */
    public function canSend(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED]);
    }

    /**
     * 即時送信かどうか
     */
    public function isImmediate(): bool
    {
        return is_null($this->scheduled_at);
    }

    /**
     * 予約送信時刻を過ぎているか
     */
    public function isScheduledTimeReached(): bool
    {
        if ($this->isImmediate()) {
            return true;
        }
        return $this->scheduled_at->lte(now());
    }

    /**
     * ステータスラベルを取得
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabels()[$this->status] ?? $this->status;
    }

    /**
     * 送信対象の顧客を取得
     */
    public function getTargetCustomers()
    {
        return Customer::where('store_id', $this->store_id)
            ->where(function ($query) {
                // LINE連携済み または メールアドレスあり
                $query->whereNotNull('line_user_id')
                      ->orWhereNotNull('email');
            })
            ->get();
    }
}
