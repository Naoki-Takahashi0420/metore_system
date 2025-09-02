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
        // 新しいフィールド
        'service_memo',
        'handled_by',
        'payment_method',
        'reservation_source',
        'visit_purpose',
        'genetic_possibility',
        'has_astigmatism',
        'eye_diseases',
        'workplace_address',
        'device_usage',
        'next_visit_notes',
        'session_number',
        'treatment_date',
        'vision_records',
    ];

    protected $casts = [
        'record_date' => 'date',
        'next_visit_date' => 'date',
        'actual_reservation_date' => 'date',
        'treatment_date' => 'date',
        'reminder_sent_at' => 'datetime',
        'medications' => 'array',
        'images' => 'array',
        'vision_records' => 'array',
        'genetic_possibility' => 'boolean',
        'has_astigmatism' => 'boolean',
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

    /**
     * 視力記録を追加
     */
    public function addVisionRecord($data)
    {
        $records = $this->vision_records ?? [];
        $records[] = array_merge($data, [
            'session' => count($records) + 1,
            'date' => now()->toDateString(),
        ]);
        $this->vision_records = $records;
        $this->session_number = count($records);
        $this->save();
    }

    /**
     * 最新の視力記録を取得
     */
    public function getLatestVisionRecord()
    {
        $records = $this->vision_records ?? [];
        return empty($records) ? null : end($records);
    }

    /**
     * 顧客に表示可能な情報を取得
     */
    public function getPublicData()
    {
        return [
            'session_number' => $this->session_number,
            'treatment_date' => $this->treatment_date,
            'vision_records' => collect($this->vision_records ?? [])->map(function ($record) {
                return [
                    'session' => $record['session'] ?? null,
                    'date' => $record['date'] ?? null,
                    'before_left' => $record['before_left'] ?? null,
                    'before_right' => $record['before_right'] ?? null,
                    'after_left' => $record['after_left'] ?? null,
                    'after_right' => $record['after_right'] ?? null,
                    'intensity' => $record['intensity'] ?? null,
                    'duration' => $record['duration'] ?? null,
                    'public_memo' => $record['public_memo'] ?? null,
                ];
            })->toArray(),
        ];
    }
}
