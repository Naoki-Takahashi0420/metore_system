<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'customer_number',
        'last_name',
        'first_name',
        'last_name_kana',
        'first_name_kana',
        'phone',
        'email',
        'birth_date',
        'gender',
        'postal_code',
        'address',
        'preferences',
        'medical_notes',
        'is_blocked',
        'sms_notifications_enabled',
        'notification_preferences',
        'last_visit_at',
        'phone_verified_at',
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
     * リレーション: 予約
     */
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * リレーション: カルテ
     */
    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class);
    }

    /**
     * 指定された予約が新規顧客の初回予約かチェック
     */
    public function isFirstReservation(Reservation $targetReservation): bool
    {
        $firstReservation = $this->reservations()
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->orderBy('reservation_date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->first();
            
        return $firstReservation && $firstReservation->id === $targetReservation->id;
    }

    /**
     * 新規顧客かどうか（有効な予約が1件以下）
     */
    public function isNewCustomer(): bool
    {
        $reservationCount = $this->reservations()
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->count();
            
        return $reservationCount <= 1;
    }
}