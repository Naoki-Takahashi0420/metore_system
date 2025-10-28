<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class NotificationLog extends Model
{
    protected $fillable = [
        'reservation_id',
        'customer_id',
        'user_id',
        'store_id',
        'notification_type',
        'channel',
        'status',
        'message_id',
        'error_code',
        'error_message',
        'recipient',
        'idempotency_key',
        'metadata',
        'sent_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
    ];

    /**
     * リレーション：予約
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * リレーション：顧客
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * リレーション：管理者
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * リレーション：店舗
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * 送信成功としてマーク
     */
    public function markAsSent(string $messageId = null, array $metadata = []): void
    {
        $this->update([
            'status' => 'sent',
            'message_id' => $messageId,
            'sent_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);
    }

    /**
     * 送信失敗としてマーク
     */
    public function markAsFailed(string $errorCode = null, string $errorMessage = null, array $metadata = []): void
    {
        $this->update([
            'status' => 'failed',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);
    }


    /**
     * Idempotency-keyの生成
     */
    public static function generateIdempotencyKey(
        string $notificationType,
        ?int $reservationId = null,
        ?int $customerId = null,
        ?int $userId = null
    ): string {
        $parts = [
            $notificationType,
            $reservationId ?? 'no-reservation',
            $customerId ?? 'no-customer',
            $userId ?? 'no-user',
            now()->format('YmdHi'), // 分単位で同一キー（1分以内の重複を防止）
        ];

        return implode(':', $parts);
    }

    /**
     * 重複チェック（過去10分以内）
     */
    public static function isDuplicate(string $idempotencyKey): bool
    {
        return self::where('idempotency_key', $idempotencyKey)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();
    }

    /**
     * チャネル別の成功率を取得（過去N日）
     */
    public static function getSuccessRateByChannel(int $days = 7): array
    {
        $results = [];
        $channels = ['line', 'sms', 'email'];

        foreach ($channels as $channel) {
            $total = self::where('channel', $channel)
                ->where('created_at', '>=', now()->subDays($days))
                ->count();

            $success = self::where('channel', $channel)
                ->where('status', 'sent')
                ->where('created_at', '>=', now()->subDays($days))
                ->count();

            $results[$channel] = [
                'total' => $total,
                'success' => $success,
                'rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
            ];
        }

        return $results;
    }
}
