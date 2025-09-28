<?php

namespace App\Listeners;

use App\Events\ReservationChanged;
use App\Services\SimpleLineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendCustomerReservationChangeNotification implements ShouldQueue
{
    use InteractsWithQueue;

    private $lineService;

    /**
     * Create the event listener.
     */
    public function __construct(SimpleLineService $lineService)
    {
        $this->lineService = $lineService;
    }

    /**
     * Handle the event.
     */
    public function handle(ReservationChanged $event): void
    {
        $oldReservation = $event->oldReservation;
        $newReservation = $event->newReservation;
        $customer = $newReservation->customer;

        if (!$customer) {
            Log::warning('Customer not found for reservation change notification', [
                'reservation_id' => $newReservation->id
            ]);
            return;
        }

        // LINE通知を送信（LINE連携済みの場合）
        if ($customer->line_user_id) {
            try {
                $message = $this->buildLineMessage($oldReservation, $newReservation);
                $this->lineService->pushMessage($customer->line_user_id, $message);

                Log::info('Reservation change LINE notification sent', [
                    'customer_id' => $customer->id,
                    'reservation_id' => $newReservation->id
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send reservation change LINE notification', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // メール通知を送信（メールアドレスがある場合）
        if ($customer->email) {
            try {
                // TODO: メール通知の実装
                Log::info('Reservation change email notification would be sent here', [
                    'customer_id' => $customer->id,
                    'email' => $customer->email,
                    'reservation_id' => $newReservation->id
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send reservation change email notification', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * LINE通知メッセージを構築
     */
    private function buildLineMessage($oldReservation, $newReservation): string
    {
        $storeName = $newReservation->store->name ?? '店舗';
        $menuName = $newReservation->menu->name ?? 'メニュー';

        $oldDate = \Carbon\Carbon::parse($oldReservation->reservation_date)->format('Y年m月d日');
        $oldTime = \Carbon\Carbon::parse($oldReservation->start_time)->format('H:i');

        $newDate = \Carbon\Carbon::parse($newReservation->reservation_date)->format('Y年m月d日');
        $newTime = \Carbon\Carbon::parse($newReservation->start_time)->format('H:i');

        $message = "【予約変更完了】\n";
        $message .= "予約の日程変更が完了しました。\n\n";
        $message .= "■変更前\n";
        $message .= "日時：{$oldDate} {$oldTime}\n\n";
        $message .= "■変更後\n";
        $message .= "日時：{$newDate} {$newTime}\n";
        $message .= "店舗：{$storeName}\n";
        $message .= "メニュー：{$menuName}\n";
        $message .= "予約番号：{$newReservation->reservation_number}\n\n";
        $message .= "ご来店をお待ちしております。";

        return $message;
    }
}