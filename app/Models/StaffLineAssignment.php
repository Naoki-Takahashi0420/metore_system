<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffLineAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'line_id',
        'date',
        'start_time',
        'end_time',
        'is_primary',
    ];

    protected $casts = [
        'date' => 'date',
        'is_primary' => 'boolean',
    ];

    /**
     * リレーション: スタッフ
     */
    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * リレーション: 予約ライン
     */
    public function line()
    {
        return $this->belongsTo(ReservationLine::class, 'line_id');
    }
}