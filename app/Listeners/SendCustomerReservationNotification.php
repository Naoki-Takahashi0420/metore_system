<?php

namespace App\Listeners;

use App\Events\ReservationCreated;
use App\Services\CustomerNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendCustomerReservationNotification implements ShouldQueue
{
    use InteractsWithQueue;

    private CustomerNotificationService $notificationService;

    /**
     * Create the event listener.
     */
    public function __construct(CustomerNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(ReservationCreated $event): void
    {
        $reservation = $event->reservation;
        
        // 予約確認通知を送信
        try {
            $result = $this->notificationService->sendReservationConfirmation($reservation);
            
            Log::info('顧客予約確認通知送信', [
                'reservation_id' => $reservation->id,
                'customer_id' => $reservation->customer_id,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('顧客予約確認通知送信失敗', [
                'reservation_id' => $reservation->id,
                'customer_id' => $reservation->customer_id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
