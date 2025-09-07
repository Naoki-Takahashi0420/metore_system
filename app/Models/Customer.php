<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'customer_number',
        'store_id',
        'last_name',
        'first_name',
        'last_name_kana',
        'first_name_kana',
        'phone',
        'email',
        'birth_date',
        'gender',
        'postal_code',
        'prefecture',
        'city',
        'address',
        'building',
        'preferences',
        'medical_notes',
        'notes',
        'is_blocked',
        'sms_notifications_enabled',
        'notification_preferences',
        'last_visit_at',
        'phone_verified_at',
        'line_user_id',
        'line_notifications_enabled',
        'line_linked_at',
        'line_profile',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'preferences' => 'array',
        'medical_notes' => 'array',
        'is_blocked' => 'boolean',
        'sms_notifications_enabled' => 'boolean',
        'notification_preferences' => 'array',
        'last_visit_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'line_notifications_enabled' => 'boolean',
        'line_linked_at' => 'datetime',
        'line_profile' => 'array',
    ];

    protected $appends = ['full_name', 'full_name_kana'];

    /**
     * フルネーム取得
     */
    public function getFullNameAttribute(): string
    {
        return $this->last_name . ' ' . $this->first_name;
    }

    /**
     * フルネーム（カナ）取得
     */
    public function getFullNameKanaAttribute(): ?string
    {
        if (!$this->last_name_kana || !$this->first_name_kana) {
            return null;
        }
        return $this->last_name_kana . ' ' . $this->first_name_kana;
    }

    /**
     * 顧客番号生成
     */
    public static function generateCustomerNumber(): string
    {
        do {
            $number = 'C' . date('Ymd') . strtoupper(Str::random(4));
        } while (self::where('customer_number', $number)->exists());

        return $number;
    }

    /**
     * リレーション: 店舗
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * リレーション: 予約
     */
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * 顧客ラベル
     */
    public function labels(): HasMany
    {
        return $this->hasMany(CustomerLabel::class);
    }

    /**
     * LINEメッセージログ
     */
    public function lineMessageLogs(): HasMany
    {
        return $this->hasMany(LineMessageLog::class);
    }

    /**
     * リレーション: カルテ
     */
    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class);
    }

    /**
     * 指定された予約が初回予約かチェック（シンプル版）
     */
    public function isFirstReservation(Reservation $targetReservation): bool
    {
        // この顧客の最初に作成された予約（ID最小）= 初回予約
        $firstReservationId = $this->reservations()
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->min('id');
            
        return $targetReservation->id === $firstReservationId;
    }

    /**
     * サブスクリプション契約（複数可）
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(CustomerSubscription::class);
    }

    /**
     * アクティブなサブスクリプション
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(CustomerSubscription::class)
            ->where('status', 'active')
            ->where('start_date', '<=', now())
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->latest();
    }

    /**
     * サブスクリプション契約中かチェック
     */
    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }

    /**
     * 特定店舗のサブスクリプションを取得
     */
    public function getSubscriptionForStore($storeId): ?CustomerSubscription
    {
        return $this->subscriptions()
            ->where('store_id', $storeId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * LINE連携済みかチェック
     */
    public function isLinkedToLine(): bool
    {
        return !empty($this->line_user_id);
    }

    /**
     * LINE通知を送信できるかチェック
     */
    public function canReceiveLineNotifications(): bool
    {
        return $this->isLinkedToLine() && $this->line_notifications_enabled;
    }

    /**
     * LINE IDで顧客を検索
     */
    public static function findByLineUserId(string $lineUserId): ?self
    {
        return self::where('line_user_id', $lineUserId)->first();
    }

    /**
     * LINEアカウント連携
     */
    public function linkToLine(string $lineUserId, array $lineProfile = null): void
    {
        $this->update([
            'line_user_id' => $lineUserId,
            'line_linked_at' => now(),
            'line_profile' => $lineProfile,
            'line_notifications_enabled' => true,
        ]);
    }

    /**
     * LINE連携解除
     */
    public function unlinkFromLine(): void
    {
        $this->update([
            'line_user_id' => null,
            'line_linked_at' => null,
            'line_profile' => null,
            'line_notifications_enabled' => false,
        ]);
    }

    /**
     * アクセストークンを取得または生成
     */
    public function getOrCreateAccessToken(Store $store = null, array $options = []): CustomerAccessToken
    {
        // 既存のアクティブなトークンをチェック
        $existingToken = CustomerAccessToken::where('customer_id', $this->id)
            ->where('store_id', $store?->id)
            ->where('purpose', 'line_linking')
            ->where('is_active', true)
            ->whereNull('expires_at')
            ->orWhere('expires_at', '>', now())
            ->first();

        if ($existingToken && $existingToken->isValid()) {
            return $existingToken;
        }

        // 新しいトークンを生成
        return CustomerAccessToken::generateFor($this, $store, array_merge([
            'purpose' => 'line_linking',
            'expires_at' => now()->addDays(30),
            'max_usage' => 1,
        ], $options));
    }
}