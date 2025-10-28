<?php

namespace App\Listeners;

use App\Events\ReservationCancelled;
use App\Services\SimpleLineService;
use App\Services\CustomerNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendCustomerReservationCancellationNotification implements ShouldQueue
{
    use InteractsWithQueue;

    private $lineService;
    private $customerNotificationService;

    /**
     * Create the event listener.
     */
    public function __construct(SimpleLineService $lineService, CustomerNotificationService $customerNotificationService)
    {
        $this->lineService = $lineService;
        $this->customerNotificationService = $customerNotificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(ReservationCancelled $event): void
    {
        $reservation = $event->reservation;
        $customer = $reservation->customer;
        $store = $reservation->store;

        if (!$customer) {
            Log::warning('Customer not found for reservation cancellation notification', [
                'reservation_id' => $reservation->id
            ]);
            return;
        }

        Log::info('📱 予約キャンセル通知開始', [
            'customer_id' => $customer->id,
            'reservation_id' => $reservation->id,
            'has_line' => !empty($customer->line_user_id),
            'has_phone' => !empty($customer->phone),
            'sms_enabled' => $customer->sms_notifications_enabled
        ]);

        // LINE通知を送信（LINE連携済みの場合）
        if ($customer->line_user_id) {
            try {
                $message = $this->buildLineMessage($reservation);
                $this->lineService->pushMessage($customer->line_user_id, $message);

                Log::info('✅ 予約キャンセルLINE通知送信成功', [
                    'customer_id' => $customer->id,
                    'reservation_id' => $reservation->id
                ]);
            } catch (\Exception $e) {
                Log::error('❌ 予約キャンセルLINE通知送信失敗', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // SMS通知を送信（電話番号があり、SMS通知が有効な場合）
        if ($customer->phone && $customer->sms_notifications_enabled) {
            try {
                $smsMessage = $this->buildSmsMessage($reservation, $customer, $store);

                // CustomerNotificationServiceを使用してSMS送信（LINE → SMS → Email fallback）
                $result = $this->customerNotificationService->sendNotification(
                    $customer,
                    $store,
                    $smsMessage,
                    'reservation_cancellation',
                    $reservation->id
                );

                if ($result['sms'] ?? $result['email'] ?? false) {
                    Log::info('✅ 予約キャンセル通知送信成功', [
                        'customer_id' => $customer->id,
                        'phone' => $customer->phone,
                        'reservation_id' => $reservation->id,
                        'channels' => $result
                    ]);
                } else {
                    Log::warning('⚠️ 予約キャンセル通知送信失敗', [
                        'customer_id' => $customer->id,
                        'phone' => $customer->phone,
                        'result' => $result
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('❌ 予約キャンセル通知エラー', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        } else {
            Log::info('ℹ️ SMS通知スキップ', [
                'customer_id' => $customer->id,
                'has_phone' => !empty($customer->phone),
                'sms_enabled' => $customer->sms_notifications_enabled
            ]);
        }
    }

    /**
     * LINE通知メッセージを構築
     */
    private function buildLineMessage($reservation): string
    {
        $storeName = $reservation->store->name ?? '店舗';
        $menuName = $reservation->menu->name ?? 'メニュー';

        $date = Carbon::parse($reservation->reservation_date)->format('Y年m月d日');
        $time = Carbon::parse($reservation->start_time)->format('H:i');

        $message = "【予約キャンセル】\n";
        $message .= "以下の予約がキャンセルされました。\n\n";
        $message .= "日時：{$date} {$time}\n";
        $message .= "店舗：{$storeName}\n";
        $message .= "メニュー：{$menuName}\n";
        $message .= "予約番号：{$reservation->reservation_number}\n\n";
        $message .= "またのご利用をお待ちしております。";

        return $message;
    }

    /**
     * SMS通知メッセージを構築
     */
    private function buildSmsMessage($reservation, $customer, $store): string
    {
        $storeName = $store->name ?? '店舗';
        $menuName = $reservation->menu->name ?? 'メニュー';

        $date = Carbon::parse($reservation->reservation_date)->format('m/d');
        $time = Carbon::parse($reservation->start_time)->format('H:i');

        $message = "【予約キャンセル】\n";
        $message .= "{$customer->last_name}様\n\n";
        $message .= "日時: {$date} {$time}\n";
        $message .= "店舗: {$storeName}\n";
        $message .= "メニュー: {$menuName}\n\n";
        $message .= "予約番号: {$reservation->reservation_number}";

        return $message;
    }
}
