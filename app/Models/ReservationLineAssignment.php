<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationLineAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'line_id',
        'start_datetime',
        'end_datetime',
        'assignment_type',
        'assignment_reason',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
    ];

    /**
     * リレーション: 予約
     */
    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * リレーション: 予約ライン
     */
    public function line()
    {
        return $this->belongsTo(ReservationLine::class, 'line_id');
    }
}