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

    protected $appends = [
        'presbyopia_before',
        'presbyopia_after',
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
     * リレーション: 添付画像
     */
    public function attachedImages()
    {
        return $this->hasMany(MedicalRecordImage::class)->orderBy('display_order', 'asc');
    }

    /**
     * リレーション: 顧客に表示可能な画像
     */
    public function visibleImages()
    {
        return $this->hasMany(MedicalRecordImage::class)
            ->where('is_visible_to_customer', true)
            ->orderBy('display_order', 'asc');
    }

    /**
     * リレーション: 老眼詳細測定記録
     */
    public function presbyopiaMeasurements()
    {
        return $this->hasMany(PresbyopiaMeasurement::class)->orderBy('status', 'asc');
    }

    /**
     * アクセサ: 施術前の老眼測定データ
     */
    public function getPresbyopiaBeforeAttribute()
    {
        return $this->presbyopiaMeasurements->where('status', '施術前')->first();
    }

    /**
     * アクセサ: 施術後の老眼測定データ
     */
    public function getPresbyopiaAfterAttribute()
    {
        return $this->presbyopiaMeasurements->where('status', '施術後')->first();
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
        // 視力記録から最新のデータを取得
        $latestVision = $this->getLatestVisionRecord();

        // 老眼詳細測定データを取得
        $beforePresbyopia = $this->presbyopiaMeasurements()->where('status', '施術前')->first();
        $afterPresbyopia = $this->presbyopiaMeasurements()->where('status', '施術後')->first();

        return [
            'session_number' => $this->session_number,
            'treatment_date' => $this->treatment_date,
            'examination_type' => $this->visit_purpose ?? '通常検査',

            // 視力データ（最新の記録から）
            'unaided_vision_right' => $latestVision['before_naked_right'] ?? $this->unaided_vision_right ?? '-',
            'unaided_vision_left' => $latestVision['before_naked_left'] ?? $this->unaided_vision_left ?? '-',
            'unaided_vision_both' => $latestVision['before_naked_both'] ?? $this->unaided_vision_both ?? '-',

            'corrected_vision_right' => $latestVision['before_corrected_right'] ?? $this->corrected_vision_right ?? '-',
            'corrected_vision_left' => $latestVision['before_corrected_left'] ?? $this->corrected_vision_left ?? '-',
            'corrected_vision_both' => $latestVision['before_corrected_both'] ?? $this->corrected_vision_both ?? '-',

            'reading_vision_right' => $this->reading_vision_right ?? '-',
            'reading_vision_left' => $this->reading_vision_left ?? '-',
            'reading_vision_both' => $this->reading_vision_both ?? '-',

            // 老眼詳細測定データ
            'presbyopia_before' => $beforePresbyopia,
            'presbyopia_after' => $afterPresbyopia,
            'has_presbyopia_data' => $beforePresbyopia || $afterPresbyopia,

            // その他の情報
            'eye_condition' => $this->eye_diseases ?? null,
            'symptoms' => $this->symptoms ?? null,
            'notes' => $this->notes ?? null,
            
            // 視力記録の履歴
            'vision_records' => collect($this->vision_records ?? [])->map(function ($record) {
                return [
                    'session' => $record['session'] ?? null,
                    'date' => $record['date'] ?? null,
                    'before_naked_left' => $record['before_naked_left'] ?? null,
                    'before_naked_right' => $record['before_naked_right'] ?? null,
                    'before_corrected_left' => $record['before_corrected_left'] ?? null,
                    'before_corrected_right' => $record['before_corrected_right'] ?? null,
                    'after_naked_left' => $record['after_naked_left'] ?? null,
                    'after_naked_right' => $record['after_naked_right'] ?? null,
                    'after_corrected_left' => $record['after_corrected_left'] ?? null,
                    'after_corrected_right' => $record['after_corrected_right'] ?? null,
                    'intensity' => $record['intensity'] ?? null,
                    'duration' => $record['duration'] ?? null,
                    'public_memo' => $record['public_memo'] ?? null,
                ];
            })->toArray(),
        ];
    }
}
