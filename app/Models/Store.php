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
        'postal_code',
        'prefecture',
        'city',
        'address',
        'phone',
        'email',
        'opening_hours',
        'holidays',
        'capacity',
        'settings',
        'reservation_settings',
        'is_active',
    ];

    protected $casts = [
        'opening_hours' => 'array',
        'holidays' => 'array',
        'settings' => 'array',
        'reservation_settings' => 'array',
        'is_active' => 'boolean',
        'capacity' => 'integer',
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
}