<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
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
        'reservation_slot_duration',
        'max_advance_days',
        'cancellation_deadline_hours',
        'require_confirmation',
        'is_active',
        'status',
        // 予約ライン設定
        'main_lines_count',
        'sub_lines_count',
        'use_staff_assignment',
        'use_equipment_management',
        'line_allocation_rules',
        // LINE設定（1店舗1LINE）
        'line_channel_access_token',
        'line_channel_secret',
        'line_official_account_id',
        'line_basic_id',
        'line_qr_code_url',
        'line_add_friend_url',
        'line_enabled',
        'line_send_reservation_confirmation',
        'line_send_reminder',
        'line_send_followup',
        'line_send_promotion',
        'line_reservation_message',
        'line_reminder_message',
        'line_followup_message_30days',
        'line_followup_message_60days',
        'line_reminder_time',
        'line_reminder_days_before',
    ];

    protected $casts = [
        'opening_hours' => 'array',
        'business_hours' => 'array',
        'holidays' => 'array',
        'settings' => 'array',
        'reservation_settings' => 'array',
        'payment_methods' => 'array',
        'is_active' => 'boolean',
        'require_confirmation' => 'boolean',
        'capacity' => 'integer',
        'reservation_slot_duration' => 'integer',
        'max_advance_days' => 'integer',
        'cancellation_deadline_hours' => 'integer',
        // 予約ライン
        'line_allocation_rules' => 'array',
        'use_staff_assignment' => 'boolean',
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
    }
}