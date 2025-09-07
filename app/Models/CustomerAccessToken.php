<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CustomerAccessToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'store_id',
        'token',
        'purpose',
        'expires_at',
        'usage_count',
        'max_usage',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'usage_count' => 'integer',
        'max_usage' => 'integer',
    ];

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
     * トークン生成
     */
    public static function generateFor(Customer $customer, ?Store $store = null, array $options = []): self
    {
        return self::create([
            'customer_id' => $customer->id,
            'store_id' => $store?->id,
            'token' => self::generateUniqueToken(),
            'purpose' => $options['purpose'] ?? 'existing_customer',
            'expires_at' => $options['expires_at'] ?? Carbon::now()->addMonths(6),
            'max_usage' => $options['max_usage'] ?? null,
            'metadata' => $options['metadata'] ?? null,
        ]);
    }

    /**
     * ユニークトークン生成
     */
    private static function generateUniqueToken(): string
    {
        do {
            $token = Str::random(32);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    /**
     * トークンが有効かチェック
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_usage && $this->usage_count >= $this->max_usage) {
            return false;
        }

        return true;
    }

    /**
     * トークン使用を記録
     */
    public function recordUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * 予約URL生成
     */
    public function getReservationUrl(): string
    {
        $baseUrl = config('app.url') . '/reservation';
        
        if ($this->store_id) {
            return "{$baseUrl}/menu/{$this->store_id}?token={$this->token}";
        }
        
        return "{$baseUrl}/store?token={$this->token}";
    }

    /**
     * QRコード用URL
     */
    public function getQrCodeUrl(): string
    {
        return $this->getReservationUrl();
    }

    /**
     * LINE友だち追加URL（QRコード用）
     */
    public function getLineAddFriendUrl(): string
    {
        if (!$this->store_id || !$this->store) {
            return '';
        }

        $store = $this->store;
        if (!$store->line_bot_basic_id) {
            return '';
        }

        // LINE友だち追加用のURL（token付き）
        return "https://line.me/R/ti/p/@{$store->line_bot_basic_id}?token={$this->token}";
    }

    /**
     * スコープ: アクティブなトークン
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * スコープ: 期限切れトークン
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }
}