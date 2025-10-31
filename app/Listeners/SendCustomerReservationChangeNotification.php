<?php

namespace App\Listeners;

use App\Events\ReservationChanged;
use App\Services\SimpleLineService;
use App\Services\CustomerNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SendCustomerReservationChangeNotification implements ShouldQueue
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
    public function handle(ReservationChanged $event): void
    {
        $oldReservationData = $event->oldReservationData;
        $newReservation = $event->newReservation;
        $customer = $newReservation->customer;
        $store = $newReservation->store;

        if (!$customer) {
            Log::warning('Customer not found for reservation change notification', [
                'reservation_id' => $newReservation->id
            ]);
            return;
        }

        // 二重送信防止: 5分間の去重鍵
        $dedupeKey = "notify:customer:change:{$newReservation->id}";
        if (!Cache::add($dedupeKey, true, now()->addMinutes(5))) {
            Log::warning('⚠️ Skip duplicate notification', [
                'deduplication_key' => $dedupeKey,
                'customer_id' => $customer->id,
                'reservation_id' => $newReservation->id,
                'reason' => 'Duplicate within 5 minutes'
            ]);
            return;
        }

        Log::info('📱 予約変更通知開始', [
            'customer_id' => $customer->id,
            'reservation_id' => $newReservation->id,
            'has_line' => !empty($customer->line_user_id),
            'has_phone' => !empty($customer->phone),
            'sms_enabled' => $customer->sms_notifications_enabled
        ]);

        // LINE通知を送信（LINE連携済みの場合）
        if ($customer->line_user_id) {
            try {
                $message = $this->buildLineMessage($oldReservationData, $newReservation);
                $this->lineService->pushMessage($customer->line_user_id, $message);

                Log::info('✅ 予約変更LINE通知送信成功', [
                    'customer_id' => $customer->id,
                    'reservation_id' => $newReservation->id
                ]);
            } catch (\Exception $e) {
                Log::error('❌ 予約変更LINE通知送信失敗', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // SMS通知を送信（電話番号があり、SMS通知が有効な場合）
        if ($customer->phone && $customer->sms_notifications_enabled) {
            try {
                $smsMessage = $this->buildSmsMessage($oldReservationData, $newReservation, $customer, $store);

                // CustomerNotificationServiceを使用してSMS送信
                $result = $this->customerNotificationService->sendNotification(
                    $customer,
                    $store,
                    $smsMessage,
                    'reservation_change',
                    $newReservation->id
                );

                if ($result['sms'] ?? false) {
                    Log::info('✅ 予約変更SMS通知送信成功', [
                        'customer_id' => $customer->id,
                        'phone' => $customer->phone,
                        'reservation_id' => $newReservation->id
                    ]);
                } else {
                    Log::warning('⚠️ 予約変更SMS通知送信失敗', [
                        'customer_id' => $customer->id,
                        'phone' => $customer->phone,
                        'result' => $result
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('❌ 予約変更SMS通知エラー', [
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
     *
     * @param array $oldReservationData 変更前の予約情報（配列）
     * @param \App\Models\Reservation $newReservation 変更後の予約（モデル）
     */
    private function buildLineMessage(array $oldReservationData, $newReservation): string
    {
        $storeName = $newReservation->store->name ?? '店舗';
        $menuName = $newReservation->menu->name ?? 'メニュー';

        $oldDate = Carbon::parse($oldReservationData['reservation_date'])->format('Y年m月d日');
        $oldTime = Carbon::parse($oldReservationData['start_time'])->format('H:i');

        $newDate = Carbon::parse($newReservation->reservation_date)->format('Y年m月d日');
        $newTime = Carbon::parse($newReservation->start_time)->format('H:i');

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

    /**
     * SMS通知メッセージを構築
     *
     * @param array $oldReservationData 変更前の予約情報（配列）
     * @param \App\Models\Reservation $newReservation 変更後の予約（モデル）
     * @param \App\Models\Customer $customer 顧客
     * @param \App\Models\Store $store 店舗
     */
    private function buildSmsMessage(array $oldReservationData, $newReservation, $customer, $store): string
    {
        $storeName = $store->name ?? '店舗';
        $menuName = $newReservation->menu->name ?? 'メニュー';

        $oldDate = Carbon::parse($oldReservationData['reservation_date'])->format('m/d');
        $oldTime = Carbon::parse($oldReservationData['start_time'])->format('H:i');

        $newDate = Carbon::parse($newReservation->reservation_date)->format('m/d');
        $newTime = Carbon::parse($newReservation->start_time)->format('H:i');

        $message = "【予約変更完了】\n";
        $message .= "{$customer->last_name}様\n\n";
        $message .= "変更前: {$oldDate} {$oldTime}\n";
        $message .= "変更後: {$newDate} {$newTime}\n";
        $message .= "店舗: {$storeName}\n";
        $message .= "メニュー: {$menuName}\n\n";
        $message .= "予約番号: {$newReservation->reservation_number}";

        return $message;
    }
}