<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'line_name',
        'line_type',
        'line_number',
        'capacity',
        'is_active',
        'allow_new_customers',
        'allow_existing_customers',
        'requires_staff',
        'allows_simultaneous',
        'equipment_id',
        'equipment_name',
        'priority',
        'availability_rules',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allow_new_customers' => 'boolean',
        'allow_existing_customers' => 'boolean',
        'requires_staff' => 'boolean',
        'allows_simultaneous' => 'boolean',
        'availability_rules' => 'array',
    ];

    /**
     * リレーション: 店舗
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * リレーション: スケジュール
     */
    public function schedules()
    {
        return $this->hasMany(ReservationLineSchedule::class, 'line_id');
    }

    /**
     * リレーション: 予約割り当て
     */
    public function assignments()
    {
        return $this->hasMany(ReservationLineAssignment::class, 'line_id');
    }

    /**
     * リレーション: スタッフ割り当て
     */
    public function staffAssignments()
    {
        return $this->hasMany(StaffLineAssignment::class, 'line_id');
    }

    /**
     * 特定の日時で利用可能かチェック
     */
    public function isAvailable($date, $startTime, $endTime)
    {
        if (!$this->is_active) {
            return false;
        }

        // スケジュールチェック
        $schedule = $this->schedules()
            ->where('date', $date)
            ->where('is_available', true)
            ->first();

        if ($schedule) {
            return $startTime >= $schedule->start_time && $endTime <= $schedule->end_time;
        }

        // availability_rulesをチェック
        if ($this->availability_rules) {
            $dayOfWeek = \Carbon\Carbon::parse($date)->dayOfWeekIso;
            $rules = $this->availability_rules;

            if (isset($rules['days']) && !in_array($dayOfWeek, $rules['days'])) {
                return false;
            }

            if (isset($rules['hours'])) {
                $hour = \Carbon\Carbon::parse($startTime)->hour;
                if ($hour < $rules['hours']['start'] || $hour >= $rules['hours']['end']) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 特定の日時での空き容量を取得
     */
    public function getAvailableCapacity($date, $startTime, $endTime)
    {
        // スケジュールによる容量上書きをチェック
        $schedule = $this->schedules()
            ->where('date', $date)
            ->first();

        $capacity = $schedule && $schedule->capacity_override !== null
            ? $schedule->capacity_override
            : $this->capacity;

        // 既存の予約数を取得
        $existingCount = $this->assignments()
            ->where(function ($query) use ($date, $startTime, $endTime) {
                $startDateTime = \Carbon\Carbon::parse($date . ' ' . $startTime);
                $endDateTime = \Carbon\Carbon::parse($date . ' ' . $endTime);
                
                $query->where(function ($q) use ($startDateTime, $endDateTime) {
                    $q->where('start_datetime', '<', $endDateTime)
                      ->where('end_datetime', '>', $startDateTime);
                });
            })
            ->count();

        return max(0, $capacity - $existingCount);
    }

    /**
     * 顧客タイプに基づいて利用可能かチェック
     */
    public function canAcceptCustomerType($isNewCustomer)
    {
        if ($isNewCustomer) {
            return $this->allow_new_customers;
        }
        return $this->allow_existing_customers;
    }

    /**
     * スコープ: アクティブなライン
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * スコープ: メインライン
     */
    public function scopeMainLines($query)
    {
        return $query->where('line_type', 'main');
    }

    /**
     * スコープ: サブライン（予備ライン）
     */
    public function scopeSubLines($query)
    {
        return $query->where('line_type', 'sub');
    }
}