<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationCancelled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $reservation;

    /**
     * Create a new event instance.
     */
    public function __construct(Reservation $reservation)
    {
        $this->reservation = $reservation;
        \Log::info('🚫 ReservationCancelled event fired', [
            'reservation_id' => $reservation->id,
            'store_id' => $reservation->store_id,
            'channels' => ['reservations.' . $reservation->store_id, 'reservations'],
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('reservations.' . $this->reservation->store_id),
            new Channel('reservations'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $reservationDate = $this->reservation->reservation_date;
        $startTime = $this->reservation->start_time;

        return [
            'id' => $this->reservation->id,
            'customer_name' => $this->reservation->customer?->full_name ?? '顧客名なし',
            'store_name' => $this->reservation->store?->name ?? '',
            'store_id' => $this->reservation->store_id,
            'reservation_date' => $reservationDate instanceof \Carbon\Carbon
                ? $reservationDate->format('Y-m-d')
                : (string)$reservationDate,
            'start_time' => is_string($startTime)
                ? $startTime
                : ($startTime?->format('H:i') ?? ''),
            'menu_name' => $this->reservation->menu?->name ?? '',
            'cancelled_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'reservation.cancelled';
    }
}
