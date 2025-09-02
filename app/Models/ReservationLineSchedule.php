<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationLineSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'line_id',
        'date',
        'start_time',
        'end_time',
        'is_available',
        'capacity_override',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'is_available' => 'boolean',
    ];

    /**
     * リレーション: 予約ライン
     */
    public function line()
    {
        return $this->belongsTo(ReservationLine::class, 'line_id');
    }
}