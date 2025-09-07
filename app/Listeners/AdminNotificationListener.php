<?php

namespace App\Listeners;

use App\Events\ReservationCreated;
use App\Events\ReservationCancelled;
use App\Events\ReservationChanged;
use App\Services\AdminNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AdminNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    private $adminNotificationService;

    /**
     * Create the event listener.
     */
    public function __construct(AdminNotificationService $adminNotificationService)
    {
        $this->adminNotificationService = $adminNotificationService;
    }

    /**
     * Handle reservation created event.
     */
    public function handleReservationCreated(ReservationCreated $event): void
    {
        $this->adminNotificationService->notifyNewReservation($event->reservation);
    }

    /**
     * Handle reservation cancelled event.
     */
    public function handleReservationCancelled(ReservationCancelled $event): void
    {
        $this->adminNotificationService->notifyReservationCancelled($event->reservation);
    }

    /**
     * Handle reservation changed event.
     */
    public function handleReservationChanged(ReservationChanged $event): void
    {
        $this->adminNotificationService->notifyReservationChanged(
            $event->oldReservation,
            $event->newReservation
        );
    }
}