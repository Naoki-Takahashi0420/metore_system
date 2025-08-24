<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'staff_id',
        'reservation_id',
        'record_date',
        'chief_complaint',
        'symptoms',
        'diagnosis',
        'treatment',
        'prescription',
        'medications',
        'medical_history',
        'notes',
        'images',
        'image_notes',
        'next_visit_date',
        'actual_reservation_date',
        'date_difference_days',
        'reservation_status',
        'reminder_sent_at',
        'created_by',
    ];

    protected $casts = [
        'record_date' => 'date',
        'next_visit_date' => 'date',
        'actual_reservation_date' => 'date',
        'reminder_sent_at' => 'datetime',
        'medications' => 'array',
        'images' => 'array',
    ];

    /**
     * リレーション: 顧客
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * リレーション: スタッフ
     */
    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * リレーション: 予約
     */
    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * リレーション: 記録作成者
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * スコープ: 日付範囲
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('record_date', [$startDate, $endDate]);
    }
}
