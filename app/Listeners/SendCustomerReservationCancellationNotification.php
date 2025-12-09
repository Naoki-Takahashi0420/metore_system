<?php

namespace App\Listeners;

use App\Events\ReservationCancelled;
use App\Services\CustomerNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SendCustomerReservationCancellationNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * トランザクションコミット後にイベントを処理
     */
    public $afterCommit = true;

    /**
     * リトライ回数
     */
    public $tries = 3;

    /**
     * リトライ間隔（秒）
     */
    public $backoff = [30, 60, 120];

    private CustomerNotificationService $customerNotificationService;

    /**
     * Create the event listener.
     */
    public function __construct(CustomerNotificationService $customerNotificationService)
    {
        $this->customerNotificationService = $customerNotificationService;
    }

    /**
     * Handle the event.
     *
     * 通知優先順位: LINE → メール → SMS（成功したら終了）
     * CustomerNotificationService.sendReservationCancellation() に統一
     */
    public function handle(ReservationCancelled $event): void
    {
        $reservation = $event->reservation;
        $customer = $reservation->customer;
        $store = $reservation->store;

        // 冪等性ガード: 既にキャンセル済みでない場合はスキップ
        if ($reservation->status !== 'cancelled') {
            Log::info('⏭️ Skip notification: reservation not cancelled', [
                'reservation_id' => $reservation->id,
                'status' => $reservation->status
            ]);
            return;
        }

        if (!$customer) {
            Log::warning('Customer not found for reservation cancellation notification', [
                'reservation_id' => $reservation->id
            ]);
            return;
        }

        // 二重送信防止: 5分間の去重鍵
        $dedupeKey = "notify:customer:cancellation:{$reservation->id}";
        if (!Cache::add($dedupeKey, true, now()->addMinutes(5))) {
            Log::warning('⚠️ Skip duplicate notification', [
                'deduplication_key' => $dedupeKey,
                'customer_id' => $customer->id,
                'reservation_id' => $reservation->id,
                'reason' => 'Duplicate within 5 minutes'
            ]);
            return;
        }

        Log::info('📱 予約キャンセル通知開始', [
            'customer_id' => $customer->id,
            'reservation_id' => $reservation->id,
            'has_line' => $customer->canReceiveLineNotifications(),
            'has_email' => !empty($customer->email),
            'has_phone' => !empty($customer->phone),
            'sms_enabled' => $customer->sms_notifications_enabled
        ]);

        // CustomerNotificationServiceを使用して通知送信
        // 優先順位: LINE → メール → SMS（成功したら終了）
        try {
            $result = $this->customerNotificationService->sendReservationCancellation($reservation);

            Log::info('✅ 予約キャンセル通知送信完了', [
                'customer_id' => $customer->id,
                'reservation_id' => $reservation->id,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('❌ 予約キャンセル通知エラー', [
                'customer_id' => $customer->id,
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
