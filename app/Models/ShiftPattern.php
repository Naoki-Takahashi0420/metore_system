<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftPattern extends Model
{
    protected $fillable = [
        'store_id',
        'name',
        'description',
        'pattern_data',
        'is_default',
        'usage_count',
    ];

    protected $casts = [
        'pattern_data' => 'array',
        'is_default' => 'boolean',
        'usage_count' => 'integer',
    ];

    /**
     * パターンデータの例：
     * [
     *     ['user_id' => 1, 'start_time' => '10:00', 'end_time' => '20:00', 'break_minutes' => 60],
     *     ['user_id' => 2, 'start_time' => '10:00', 'end_time' => '15:00', 'break_minutes' => 0],
     *     ['user_id' => 3, 'start_time' => null, 'end_time' => null], // 休み
     * ]
     */

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * パターンを特定の日付に適用
     */
    public function applyToDate($date, $storeId)
    {
        $appliedShifts = [];
        
        foreach ($this->pattern_data as $staffPattern) {
            if ($staffPattern['start_time'] && $staffPattern['end_time']) {
                $shift = Shift::create([
                    'user_id' => $staffPattern['user_id'],
                    'store_id' => $storeId,
                    'shift_date' => $date,
                    'start_time' => $staffPattern['start_time'],
                    'end_time' => $staffPattern['end_time'],
                    'break_start' => $this->calculateBreakTime($staffPattern['start_time'], $staffPattern['break_minutes']),
                    'break_end' => $this->calculateBreakEnd($staffPattern['start_time'], $staffPattern['break_minutes']),
                    'status' => 'scheduled',
                    'is_available_for_reservation' => true,
                ]);
                $appliedShifts[] = $shift;
            }
        }
        
        // 使用回数をインクリメント
        $this->increment('usage_count');
        
        return $appliedShifts;
    }

    private function calculateBreakTime($startTime, $breakMinutes)
    {
        if (!$breakMinutes) return null;
        
        // 開始時間から3時間後に休憩開始（簡易ロジック）
        $start = \Carbon\Carbon::parse($startTime);
        return $start->addHours(3)->format('H:i:s');
    }

    private function calculateBreakEnd($startTime, $breakMinutes)
    {
        if (!$breakMinutes) return null;
        
        $breakStart = $this->calculateBreakTime($startTime, $breakMinutes);
        $break = \Carbon\Carbon::parse($breakStart);
        return $break->addMinutes($breakMinutes)->format('H:i:s');
    }
}