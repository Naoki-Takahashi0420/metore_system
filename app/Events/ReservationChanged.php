<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $oldReservationData; // 配列で保存（シリアライズ問題を回避）
    public Reservation $newReservation;

    /**
     * Create a new event instance.
     *
     * @param array $oldReservationData 変更前の予約情報（配列）
     * @param Reservation $newReservation 変更後の予約（モデル）
     */
    public function __construct(array $oldReservationData, Reservation $newReservation)
    {
        $this->oldReservationData = $oldReservationData;
        $this->newReservation = $newReservation;
    }
}