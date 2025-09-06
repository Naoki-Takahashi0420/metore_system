<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id',
        'shift_date',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
        'additional_breaks',
        'status',
        'notes',
        'is_available_for_reservation',
        'actual_start_time',
        'actual_break_start',
        'actual_break_end',
        'actual_end_time',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'is_available_for_reservation' => 'boolean',
        'additional_breaks' => 'array',
    ];

    /**
     * リレーション: スタッフ
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * リレーション: 店舗
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * リレーション: 予約
     */
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * 実働時間を計算（休憩時間を除く）
     */
    public function getWorkingHoursAttribute(): float
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);
        $totalMinutes = $end->diffInMinutes($start);
        
        if ($this->break_start && $this->break_end) {
            $breakStart = Carbon::parse($this->break_start);
            $breakEnd = Carbon::parse($this->break_end);
            $breakMinutes = $breakEnd->diffInMinutes($breakStart);
            $totalMinutes -= $breakMinutes;
        }
        
        return round($totalMinutes / 60, 1);
    }
    
    /**
     * 実際の実働時間を計算
     */
    public function getActualWorkingHoursAttribute(): ?float
    {
        if (!$this->actual_start_time || !$this->actual_end_time) {
            return null;
        }
        
        $start = Carbon::parse($this->actual_start_time);
        $end = Carbon::parse($this->actual_end_time);
        $totalMinutes = $end->diffInMinutes($start);
        
        if ($this->actual_break_start && $this->actual_break_end) {
            $breakStart = Carbon::parse($this->actual_break_start);
            $breakEnd = Carbon::parse($this->actual_break_end);
            $breakMinutes = $breakEnd->diffInMinutes($breakStart);
            $totalMinutes -= $breakMinutes;
        }
        
        return round($totalMinutes / 60, 1);
    }
    
    /**
     * 勤怠ステータスを取得
     */
    public function getAttendanceStatusAttribute(): string
    {
        if ($this->actual_end_time) return 'completed';
        if ($this->actual_break_start && !$this->actual_break_end) return 'on_break';
        if ($this->actual_start_time) return 'working';
        return 'not_started';
    }

    /**
     * シフトが現在進行中かチェック
     */
    public function getIsActiveAttribute(): bool
    {
        if ($this->shift_date->isToday()) {
            $now = now()->format('H:i:s');
            return $now >= $this->start_time && $now <= $this->end_time;
        }
        return false;
    }

    /**
     * スコープ: 今日のシフト
     */
    public function scopeToday($query)
    {
        return $query->whereDate('shift_date', today());
    }

    /**
     * スコープ: 今週のシフト
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('shift_date', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    /**
     * スコープ: 特定月のシフト
     */
    public function scopeForMonth($query, $year, $month)
    {
        return $query->whereYear('shift_date', $year)
                     ->whereMonth('shift_date', $month);
    }

    /**
     * スコープ: 予約可能なシフト
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available_for_reservation', true)
                     ->where('status', 'scheduled');
    }
}