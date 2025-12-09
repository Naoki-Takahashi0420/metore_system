<?php

namespace App\Listeners;

use App\Events\ReservationChanged;
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
     * CustomerNotificationService.sendReservationChange() に統一
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
            'has_line' => $customer->canReceiveLineNotifications(),
            'has_email' => !empty($customer->email),
            'has_phone' => !empty($customer->phone),
            'sms_enabled' => $customer->sms_notifications_enabled
        ]);

        // 変更内容を構築
        $changes = $this->buildChanges($oldReservationData, $newReservation);

        // CustomerNotificationServiceを使用して通知送信
        // 優先順位: LINE → メール → SMS（成功したら終了）
        try {
            $result = $this->customerNotificationService->sendReservationChange($newReservation, $changes);

            Log::info('✅ 予約変更通知送信完了', [
                'customer_id' => $customer->id,
                'reservation_id' => $newReservation->id,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('❌ 予約変更通知エラー', [
                'customer_id' => $customer->id,
                'reservation_id' => $newReservation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 変更内容を構築
     *
     * @param array $oldReservationData 変更前の予約情報（配列）
     * @param \App\Models\Reservation $newReservation 変更後の予約（モデル）
     * @return array
     */
    private function buildChanges(array $oldReservationData, $newReservation): array
    {
        $changes = [];

        // 日付の変更
        $oldDate = $oldReservationData['reservation_date'] ?? null;
        $newDate = $newReservation->reservation_date;
        if ($oldDate && $oldDate != $newDate) {
            $changes['reservation_date'] = [
                'old' => Carbon::parse($oldDate)->format('Y年m月d日'),
                'new' => Carbon::parse($newDate)->format('Y年m月d日'),
            ];
        }

        // 時間の変更
        $oldTime = $oldReservationData['start_time'] ?? null;
        $newTime = $newReservation->start_time;
        if ($oldTime && $oldTime != $newTime) {
            $changes['start_time'] = [
                'old' => Carbon::parse($oldTime)->format('H:i'),
                'new' => Carbon::parse($newTime)->format('H:i'),
            ];
        }

        // メニューの変更
        $oldMenuId = $oldReservationData['menu_id'] ?? null;
        $newMenuId = $newReservation->menu_id;
        if ($oldMenuId && $oldMenuId != $newMenuId) {
            $oldMenuName = \App\Models\Menu::find($oldMenuId)?->name ?? '不明';
            $newMenuName = $newReservation->menu?->name ?? '不明';
            $changes['menu'] = [
                'old' => $oldMenuName,
                'new' => $newMenuName,
            ];
        }

        // 金額の変更
        $oldAmount = $oldReservationData['total_amount'] ?? null;
        $newAmount = $newReservation->total_amount;
        if ($oldAmount !== null && $oldAmount != $newAmount) {
            $changes['total_amount'] = [
                'old' => $oldAmount,
                'new' => $newAmount,
            ];
        }

        return $changes;
    }
}