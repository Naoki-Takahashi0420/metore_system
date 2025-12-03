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
        'characteristics', // 顧客特性（スタッフ用）
        'is_blocked',
        'ignore_interval_rule',
        'risk_override',
        'risk_flag_source',
        'risk_flag_reason',
        'risk_flagged_at',
        'cancellation_count',
        'no_show_count',
        'change_count',
        'last_cancelled_at',
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
        'ignore_interval_rule' => 'boolean',
        'risk_override' => 'boolean',
        'risk_flag_reason' => 'array',
        'risk_flagged_at' => 'datetime',
        'cancellation_count' => 'integer',
        'no_show_count' => 'integer',
        'change_count' => 'integer',
        'last_cancelled_at' => 'datetime',
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
     * グローバル検索用スコープ
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            // 名前での検索
            $q->where('last_name', 'like', "%{$search}%")
              ->orWhere('first_name', 'like', "%{$search}%")
              ->orWhere('last_name_kana', 'like', "%{$search}%")
              ->orWhere('first_name_kana', 'like', "%{$search}%")
              ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", ["%{$search}%"])
              ->orWhereRaw("CONCAT(last_name_kana, ' ', first_name_kana) LIKE ?", ["%{$search}%"]);

            // 電話番号での検索（ハイフンありなし両方対応）
            $searchPlain = preg_replace('/[^0-9]/', '', $search);
            if ($searchPlain) {
                $q->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$searchPlain}%")
                  ->orWhereRaw("REPLACE(phone, '-', '') LIKE ?", ["%{$searchPlain}%"]);
            }

            // メールアドレスでの検索
            $q->orWhere('email', 'like', "%{$search}%");
        });
    }

    /**
     * 名前・カナ検索用スコープ（フルネーム対応）
     */
    public function scopeSearchByName($query, $search)
    {
        // スペースを除去した検索語
        $searchNoSpace = str_replace(' ', '', $search);

        return $query->where(function ($q) use ($search, $searchNoSpace) {
            // 姓での検索
            $q->where('last_name', 'like', "%{$search}%")
              // 名での検索
              ->orWhere('first_name', 'like', "%{$search}%")
              // 姓（カナ）での検索
              ->orWhere('last_name_kana', 'like', "%{$search}%")
              // 名（カナ）での検索
              ->orWhere('first_name_kana', 'like', "%{$search}%")
              // フルネーム（スペースあり）での検索
              ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", ["%{$search}%"])
              // フルネーム（スペースなし）での検索
              ->orWhereRaw("CONCAT(last_name, first_name) LIKE ?", ["%{$searchNoSpace}%"])
              // フルネームカナ（スペースあり）での検索
              ->orWhereRaw("CONCAT(last_name_kana, ' ', first_name_kana) LIKE ?", ["%{$search}%"])
              // フルネームカナ（スペースなし）での検索
              ->orWhereRaw("CONCAT(last_name_kana, first_name_kana) LIKE ?", ["%{$searchNoSpace}%"]);
        });
    }

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
     * リレーション: 顧客画像
     */
    public function images()
    {
        return $this->hasMany(CustomerImage::class);
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
     * 回数券（複数可）
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(CustomerTicket::class);
    }

    /**
     * 利用可能な回数券（有効期限内 & 残回数あり）
     */
    public function activeTickets(): HasMany
    {
        return $this->hasMany(CustomerTicket::class)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->whereColumn('used_count', '<', 'total_count');
    }

    /**
     * 特定店舗で利用可能な回数券を取得（優先順位順）
     */
    public function getAvailableTicketsForStore(int $storeId)
    {
        return $this->activeTickets()
            ->where('store_id', $storeId)
            ->orderByRaw('expires_at IS NULL')  // 無期限を最後に
            ->orderBy('expires_at', 'asc')      // 期限が近い順
            ->orderBy('total_count', 'desc')     // 残回数が少ない順（total_countで代用）
            ->orderBy('purchased_at', 'asc')    // 購入日が古い順
            ->get();
    }

    /**
     * アクティブなサブスクリプション
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(CustomerSubscription::class)
            ->where('status', 'active')
            ->where(function ($query) {
                // start_dateが空の場合はservice_start_dateかbilling_start_dateを使う
                $query->where(function ($q) {
                    $q->whereNotNull('start_date')
                      ->where('start_date', '<=', now());
                })->orWhere(function ($q) {
                    $q->whereNull('start_date')
                      ->whereNotNull('service_start_date')
                      ->where('service_start_date', '<=', now());
                })->orWhere(function ($q) {
                    $q->whereNull('start_date')
                      ->whereNull('service_start_date')
                      ->whereNotNull('billing_start_date')
                      ->where('billing_start_date', '<=', now());
                });
            })
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
     * 要注意顧客かどうかチェック
     * is_blocked の値を尊重（手動上書きも含めて最終判定結果を返す）
     */
    public function isHighRisk(): bool
    {
        // is_blocked が最終判定結果（自動判定 + 手動上書き）
        return (bool) $this->is_blocked;
    }

    /**
     * 要注意レベルを取得
     * is_blocked=false の場合は 'safe'、true の場合はカウントに基づいてレベルを返す
     */
    public function getRiskLevel(): string
    {
        // is_blocked=false の場合は安全（手動でOFFにした場合も含む）
        if (!$this->is_blocked) {
            return 'safe';
        }

        // is_blocked=true の場合、カウントに基づいてレベルを判定
        if ($this->cancellation_count >= 3 || $this->no_show_count >= 2) {
            return 'high';
        }
        if ($this->cancellation_count >= 1 || $this->no_show_count >= 1 || $this->change_count >= 3) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * 自動リスク判定を実行（is_blockedの自動更新）
     * risk_override=false の場合のみ実行
     */
    public function evaluateRiskStatus(): void
    {
        // 手動上書きされている場合は自動判定を行わない
        if ($this->risk_override) {
            \Log::info('[Customer::evaluateRiskStatus] Skipped (manual override)', [
                'customer_id' => $this->id,
                'risk_override' => true,
            ]);
            return;
        }

        $thresholds = config('customer_risk.thresholds');
        $shouldBlock = false;
        $reasons = [];

        // キャンセル: 直近90日で2回以上
        $recentCancellations = $this->getRecentReservations('cancelled', $thresholds['cancellation']['days']);
        if ($recentCancellations->count() >= $thresholds['cancellation']['count']) {
            $shouldBlock = true;
            $reasons[] = [
                'type' => 'cancellation',
                'count' => $recentCancellations->count(),
                'threshold' => $thresholds['cancellation']['count'],
                'days' => $thresholds['cancellation']['days'],
                'reservation_ids' => $recentCancellations->pluck('id')->toArray(),
            ];
        }

        // ノーショー: 直近180日で1回以上
        $recentNoShows = $this->getRecentReservations('no_show', $thresholds['no_show']['days']);
        if ($recentNoShows->count() >= $thresholds['no_show']['count']) {
            $shouldBlock = true;
            $reasons[] = [
                'type' => 'no_show',
                'count' => $recentNoShows->count(),
                'threshold' => $thresholds['no_show']['count'],
                'days' => $thresholds['no_show']['days'],
                'reservation_ids' => $recentNoShows->pluck('id')->toArray(),
            ];
        }

        // 予約変更: 直近60日で3回以上
        // TODO: change_count は Reservation に記録されていないため、
        // 現状は customers.change_count をそのまま使用
        // より正確には Reservationの更新履歴を追跡する必要がある
        if ($this->change_count >= $thresholds['change']['count']) {
            $shouldBlock = true;
            $reasons[] = [
                'type' => 'change',
                'count' => $this->change_count,
                'threshold' => $thresholds['change']['count'],
                'days' => $thresholds['change']['days'],
            ];
        }

        // is_blocked の更新（状態が変わる場合のみ）
        $oldBlocked = $this->is_blocked;

        if ($oldBlocked !== $shouldBlock) {
            $this->is_blocked = $shouldBlock;
            $this->risk_flag_source = 'auto';
            $this->risk_flag_reason = $shouldBlock ? $reasons : null; // OFFの場合は理由クリア
            $this->risk_flagged_at = now();

            $this->saveQuietly(); // Observer を発火させない

            \Log::info('[Customer::evaluateRiskStatus] is_blocked changed by auto evaluation', [
                'customer_id' => $this->id,
                'old_blocked' => $oldBlocked,
                'new_blocked' => $shouldBlock,
                'reasons' => $reasons,
                'action' => $shouldBlock ? 'BLOCKED' : 'UNBLOCKED',
            ]);
        } else {
            \Log::info('[Customer::evaluateRiskStatus] No change needed', [
                'customer_id' => $this->id,
                'current_blocked' => $oldBlocked,
                'evaluated_should_block' => $shouldBlock,
            ]);
        }
    }

    /**
     * 直近N日間の特定ステータスの予約を取得（カウント対象外を除外）
     */
    public function getRecentReservations(string $status, int $days)
    {
        return $this->reservations()
            ->where('status', $status)
            ->where('created_at', '>=', now()->subDays($days))
            ->where(function ($query) {
                // cancel_reason が設定されている場合、カウント対象外を除外
                $query->whereNull('cancel_reason')
                      ->orWhere(function ($q) {
                          $excludeReasons = collect(config('customer_risk.cancel_reasons'))
                              ->filter(fn($config) => $config['exclude_from_count'])
                              ->keys()
                              ->toArray();

                          if (!empty($excludeReasons)) {
                              $q->whereNotIn('cancel_reason', $excludeReasons);
                          }
                      });
            })
            ->get();
    }

    /**
     * cancel_reason がカウント対象外かチェック
     */
    public static function shouldExcludeFromCount(?string $cancelReason): bool
    {
        if (!$cancelReason) {
            return false;
        }

        $config = config("customer_risk.cancel_reasons.{$cancelReason}");
        return $config['exclude_from_count'] ?? false;
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
    public function linkToLine(string $lineUserId, ?array $lineProfile = null): void
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
    public function getOrCreateAccessToken(?Store $store = null, array $options = []): CustomerAccessToken
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

        // 6桁の連携コードを生成
        $linkingCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        
        // 新しいトークンを生成（連携コード付き）
        return CustomerAccessToken::generateFor($this, $store, array_merge([
            'purpose' => 'line_linking',
            'expires_at' => now()->addDays(30),
            'max_usage' => 1,
            'metadata' => array_merge($options['metadata'] ?? [], [
                'linking_code' => $linkingCode
            ])
        ], $options));
    }
}