<?php

namespace App\Listeners;

use App\Events\ReservationCreated;
use App\Services\CustomerNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SendCustomerReservationNotification implements ShouldQueue
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

        // 店舗スタッフが対面で対応した予約は通知をスキップ
        $skipSources = ['phone', 'walk_in', 'admin'];
        if (in_array($reservation->source, $skipSources)) {
            Log::info('顧客予約確認通知スキップ（店舗対応）', [
                'reservation_id' => $reservation->id,
                'customer_id' => $reservation->customer_id,
                'source' => $reservation->source
            ]);
            return;
        }

        // 既に確認通知送信済みの場合はスキップ（LINE連携時に送信済みの場合など）
        // confirmation_sent_at フラグで統一的にチェック
        if ($reservation->confirmation_sent_at) {
            Log::info('顧客予約確認通知スキップ（送信済み）', [
                'reservation_id' => $reservation->id,
                'customer_id' => $reservation->customer_id,
                'sent_at' => $reservation->confirmation_sent_at
            ]);
            return;
        }

        // 二重送信防止: 5分間の去重鍵
        $dedupeKey = "notify:customer:confirmation:{$reservation->id}";
        if (!Cache::add($dedupeKey, true, now()->addMinutes(5))) {
            Log::warning('⚠️ Skip duplicate notification', [
                'deduplication_key' => $dedupeKey,
                'reservation_id' => $reservation->id,
                'customer_id' => $reservation->customer_id,
                'reason' => 'Duplicate within 5 minutes'
            ]);
            return;
        }

        // 予約確認通知を送信
        try {
            $result = $this->notificationService->sendReservationConfirmation($reservation);

            Log::info('顧客予約確認通知送信', [
                'reservation_id' => $reservation->id,
                'customer_id' => $reservation->customer_id,
                'result' => $result
            ]);

            // 送信成功時はフラグを設定（統一的な管理）
            if (!empty($result['line']) || !empty($result['sms'])) {
                $reservation->update([
                    'confirmation_sent' => true,
                    'confirmation_sent_at' => now(),
                    'confirmation_method' => !empty($result['line']) ? 'line' : 'sms'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('顧客予約確認通知送信失敗', [
                'reservation_id' => $reservation->id,
                'customer_id' => $reservation->customer_id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
