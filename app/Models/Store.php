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