<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'staff_id',
        'shift_date',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
        'status',
        'actual_start',
        'actual_end',
        'notes',
    ];

    protected $casts = [
        'shift_date' => 'date',
    ];

    /**
     * リレーション: 店舗
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * リレーション: スタッフ
     */
    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * スコープ: 日付範囲
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('shift_date', [$startDate, $endDate]);
    }

    /**
     * スコープ: ステータス別
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * 勤務時間を計算（分）
     */
    public function getWorkingMinutesAttribute(): int
    {
        $start = $this->actual_start ?: $this->start_time;
        $end = $this->actual_end ?: $this->end_time;

        $startTime = $this->shift_date->copy()->setTimeFromTimeString($start);
        $endTime = $this->shift_date->copy()->setTimeFromTimeString($end);

        $totalMinutes = $startTime->diffInMinutes($endTime);

        // 休憩時間を引く
        if ($this->break_start && $this->break_end) {
            $breakStart = $this->shift_date->copy()->setTimeFromTimeString($this->break_start);
            $breakEnd = $this->shift_date->copy()->setTimeFromTimeString($this->break_end);
            $breakMinutes = $breakStart->diffInMinutes($breakEnd);
            $totalMinutes -= $breakMinutes;
        }

        return $totalMinutes;
    }
}
