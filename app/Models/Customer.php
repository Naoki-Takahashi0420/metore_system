<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Model
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
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
        'last_visit_at',
        'phone_verified_at',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'preferences' => 'array',
        'medical_notes' => 'array',
        'is_blocked' => 'boolean',
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
}