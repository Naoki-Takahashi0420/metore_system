<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $oldReservation;
    public $newReservation;

    /**
     * Create a new event instance.
     */
    public function __construct(Reservation $oldReservation, Reservation $newReservation)
    {
        $this->oldReservation = $oldReservation;
        $this->newReservation = $newReservation;
    }
}