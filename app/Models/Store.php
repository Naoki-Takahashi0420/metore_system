<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'company_name', // 法人名（請求書に表示）
        'company_postal_code', // 法人郵便番号
        'company_address', // 法人住所
        'company_phone', // 法人電話番号
        'company_contact_person', // 担当者名
        'fc_type', // headquarters=本部, fc_store=加盟店, regular=通常店舗
        'headquarters_store_id', // 本部店舗ID（加盟店の場合）
        'name_kana',
        'code',
        'postal_code',
        'prefecture',
        'city',
        'address',
        'phone',
        'image_path',
        'email',
        'description',
        'access',
        'opening_hours',
        'business_hours',
        'holidays',
        'capacity',
        'settings',
        'reservation_settings',
        'payment_methods',
        'visit_sources',
        'reservation_slot_duration',
        'max_advance_days',
        'min_interval_days',
        'cancellation_deadline_hours',
        'require_confirmation',
        'is_active',
        'status',
        // 予約ライン設定
        'main_lines_count',
        'sub_lines_count',
        'shift_based_capacity',
        'use_staff_assignment',
        'mode_change_date',
        'future_use_staff_assignment',
        'use_equipment_management',
        'line_allocation_rules',
        // LINE設定（1店舗1LINE）
        'line_channel_access_token',
        'line_channel_secret',
        'line_channel_id',
        'line_liff_id',
        'line_official_account_id',
        'line_basic_id',
        'line_bot_basic_id',
        'line_qr_code_url',
        'line_add_friend_url',
        'line_enabled',
        'line_send_reservation_confirmation',
        'line_send_reminder',
        'line_send_followup',
        'line_send_promotion',
        'line_reservation_message',
        'line_reminder_message',
        'line_followup_message_7days',
        'line_followup_message_15days',
        'line_followup_message_30days',
        'line_followup_message_60days',
        'line_reminder_time',
        'line_reminder_days_before',
        // 振込先情報
        'bank_name',
        'bank_branch',
        'bank_account_type',
        'bank_account_number',
        'bank_account_name',
        'bank_transfer_note',
        // 店舗メモ
        'memo',
    ];

    protected $casts = [
        'opening_hours' => 'array',
        'business_hours' => 'array',
        'holidays' => 'array',
        'settings' => 'array',
        'reservation_settings' => 'array',
        'payment_methods' => 'array',
        'visit_sources' => 'array',
        'is_active' => 'boolean',
        'require_confirmation' => 'boolean',
        'capacity' => 'integer',
        'reservation_slot_duration' => 'integer',
        'max_advance_days' => 'integer',
        'min_interval_days' => 'integer',
        'cancellation_deadline_hours' => 'integer',
        // 予約ライン
        'line_allocation_rules' => 'array',
        'use_staff_assignment' => 'boolean',
        'future_use_staff_assignment' => 'boolean',
        'mode_change_date' => 'date',
        'shift_based_capacity' => 'integer',
        'use_equipment_management' => 'boolean',
        // LINE設定
        'line_enabled' => 'boolean',
        'line_send_reservation_confirmation' => 'boolean',
        'line_send_reminder' => 'boolean',
        'line_send_followup' => 'boolean',
        'line_send_promotion' => 'boolean',
    ];

    /**
     * リレーション: スタッフ
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * リレーション: メニュー
     */
    public function menus()
    {
        return $this->hasMany(Menu::class);
    }

    /**
     * リレーション: 予約
     */
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * リレーション: シフトスケジュール
     */
    public function shiftSchedules()
    {
        return $this->hasMany(ShiftSchedule::class);
    }

    /**
     * リレーション: 店舗管理者
     */
    public function managers()
    {
        return $this->belongsToMany(User::class, 'store_managers', 'store_id', 'user_id')
                    ->withTimestamps();
    }

    // ========== FC関連リレーション ==========

    /**
     * 本部店舗（この店舗が加盟店の場合）
     */
    public function headquartersStore()
    {
        return $this->belongsTo(Store::class, 'headquarters_store_id');
    }

    /**
     * 加盟店（この店舗が本部の場合）
     */
    public function fcStores()
    {
        return $this->hasMany(Store::class, 'headquarters_store_id');
    }

    /**
     * FC商品カテゴリ（本部として）
     */
    public function fcProductCategories()
    {
        return $this->hasMany(FcProductCategory::class, 'headquarters_store_id');
    }

    /**
     * FC商品（本部として）
     */
    public function fcProducts()
    {
        return $this->hasMany(FcProduct::class, 'headquarters_store_id');
    }

    /**
     * 発注（FC店舗として）
     */
    public function fcOrdersAsStore()
    {
        return $this->hasMany(FcOrder::class, 'fc_store_id');
    }

    /**
     * 受注（本部として）
     */
    public function fcOrdersAsHeadquarters()
    {
        return $this->hasMany(FcOrder::class, 'headquarters_store_id');
    }

    /**
     * 請求書（FC店舗として）
     */
    public function fcInvoicesAsStore()
    {
        return $this->hasMany(FcInvoice::class, 'fc_store_id');
    }

    /**
     * 請求書（本部として）
     */
    public function fcInvoicesAsHeadquarters()
    {
        return $this->hasMany(FcInvoice::class, 'headquarters_store_id');
    }

    /**
     * 本部店舗かどうか
     */
    public function isHeadquarters(): bool
    {
        return $this->fc_type === 'headquarters';
    }

    /**
     * FC加盟店かどうか
     */
    public function isFcStore(): bool
    {
        return $this->fc_type === 'fc_store';
    }

    /**
     * 通常店舗かどうか
     */
    public function isRegularStore(): bool
    {
        return $this->fc_type === 'regular' || empty($this->fc_type);
    }

    /**
     * 店舗コードを自動生成
     */
    public static function generateStoreCode(): string
    {
        do {
            $code = 'ST' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (static::where('code', $code)->exists());
        
        return $code;
    }

    /**
     * 営業中かチェック
     */
    public function isOpen(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();
        $dayOfWeek = strtolower($now->format('l'));
        
        $hours = $this->opening_hours[$dayOfWeek] ?? null;
        
        if (!$hours || !isset($hours['open']) || !isset($hours['close'])) {
            return false;
        }

        $openTime = $now->copy()->setTimeFromTimeString($hours['open']);
        $closeTime = $now->copy()->setTimeFromTimeString($hours['close']);

        return $now->between($openTime, $closeTime);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($store) {
            if (empty($store->code)) {
                $store->code = static::generateStoreCode();
            }
        });
        
        static::saving(function ($store) {
            // 予約枠の長さを15分、30分、60分のいずれかに制限
            if (!in_array($store->reservation_slot_duration, [15, 30, 60])) {
                $store->reservation_slot_duration = 30; // デフォルト値
            }
        });
    }

    /**
     * リレーション: 一斉送信メッセージ
     */
    public function broadcastMessages()
    {
        return $this->hasMany(BroadcastMessage::class);
    }
}