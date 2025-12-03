<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationChanged implements ShouldBroadcast
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

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('reservations.' . $this->newReservation->store_id),
            new Channel('reservations'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $reservationDate = $this->newReservation->reservation_date;
        $startTime = $this->newReservation->start_time;

        return [
            'id' => $this->newReservation->id,
            'customer_name' => $this->newReservation->customer?->full_name ?? '顧客名なし',
            'store_name' => $this->newReservation->store?->name ?? '',
            'store_id' => $this->newReservation->store_id,
            'reservation_date' => $reservationDate instanceof \Carbon\Carbon
                ? $reservationDate->format('Y-m-d')
                : (string)$reservationDate,
            'start_time' => is_string($startTime)
                ? $startTime
                : ($startTime?->format('H:i') ?? ''),
            'menu_name' => $this->newReservation->menu?->name ?? '',
            'old_date' => $this->oldReservationData['reservation_date'] ?? '',
            'old_time' => $this->oldReservationData['start_time'] ?? '',
            'changed_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'reservation.changed';
    }
}
