<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Store;
use App\Models\Reservation;
use App\Services\SimpleLineService;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CustomerNotificationService
{
    private SimpleLineService $lineService;
    private SmsService $smsService;

    public function __construct(SimpleLineService $lineService, SmsService $smsService)
    {
        $this->lineService = $lineService;
        $this->smsService = $smsService;
    }

    /**
     * 顧客に通知を送信（LINE優先、SMS代替）
     */
    public function sendNotification(Customer $customer, Store $store, string $message, string $notificationType = 'general'): array
    {
        $results = [];

        // デバッグログ追加
        Log::info('🔔 顧客通知送信開始', [
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'type' => $notificationType,
            'has_line' => $customer->canReceiveLineNotifications(),
            'line_user_id' => $customer->line_user_id,
            'has_phone' => !empty($customer->phone),
            'sms_enabled' => $customer->sms_notifications_enabled
        ]);

        // LINE連携済みの場合は LINE > SMS の順で試行
        if ($customer->canReceiveLineNotifications()) {
            $lineResult = $this->sendLineNotification($customer, $store, $message, $notificationType);
            $results['line'] = $lineResult;

            if ($lineResult) {
                Log::info('✅ 顧客通知成功 (LINE) - SMS送信をスキップ', [
                    'customer_id' => $customer->id,
                    'store_id' => $store->id,
                    'type' => $notificationType
                ]);
                // LINE送信成功時はSMSを送信しない
                $results['sms'] = false;
                return $results;
            }

            Log::warning('⚠️ LINE通知失敗、SMSにフォールバック', [
                'customer_id' => $customer->id,
                'store_id' => $store->id
            ]);
        }

        // LINE送信失敗またはLINE未連携の場合はSMSを試行
        if ($customer->phone && $customer->sms_notifications_enabled) {
            Log::info('📱 SMS送信を試行', [
                'customer_id' => $customer->id,
                'phone' => $customer->phone
            ]);
            $smsResult = $this->sendSmsNotification($customer, $message, $notificationType);
            $results['sms'] = $smsResult;
            
            if ($smsResult) {
                Log::info('顧客通知成功 (SMS)', [
                    'customer_id' => $customer->id,
                    'store_id' => $store->id,
                    'type' => $notificationType
                ]);
            } else {
                Log::error('SMS通知も失敗', [
                    'customer_id' => $customer->id,
                    'store_id' => $store->id
                ]);
            }
        } else {
            Log::warning('SMS通知スキップ (電話番号なし or SMS通知無効)', [
                'customer_id' => $customer->id,
                'has_phone' => !empty($customer->phone),
                'sms_enabled' => $customer->sms_notifications_enabled
            ]);
            $results['sms'] = false;
        }
        
        return $results;
    }

    /**
     * 予約リマインダー送信
     */
    public function sendReservationReminder(Reservation $reservation): array
    {
        $customer = $reservation->customer;
        $store = $reservation->store;
        
        $date = Carbon::parse($reservation->reservation_date)->format('Y年n月j日');
        $time = Carbon::parse($reservation->start_time)->format('H:i');
        
        $message = "【予約リマインダー】\n{$customer->last_name} {$customer->first_name}様\n\n明日のご予約をお忘れなく！\n\n店舗: {$store->name}\n日時: {$date} {$time}〜\nメニュー: {$reservation->menu->name}\n\nご質問がございましたらお気軽にお電話ください。\n{$store->phone}";
        
        return $this->sendNotification($customer, $store, $message, 'reservation_reminder');
    }

    /**
     * 予約確認通知送信
     */
    public function sendReservationConfirmation(Reservation $reservation): array
    {
        $customer = $reservation->customer;
        $store = $reservation->store;
        
        $date = Carbon::parse($reservation->reservation_date)->format('Y年n月j日');
        $time = Carbon::parse($reservation->start_time)->format('H:i');
        
        $message = "【予約確認】\n{$customer->last_name} {$customer->first_name}様\n\nご予約ありがとうございます！\n\n予約番号: {$reservation->reservation_number}\n店舗: {$store->name}\n日時: {$date} {$time}〜\nメニュー: {$reservation->menu->name}\n料金: ¥" . number_format($reservation->total_amount) . "\n\n当日は5分前にお越しください。\n{$store->phone}";
        
        return $this->sendNotification($customer, $store, $message, 'reservation_confirmation');
    }

    /**
     * 予約変更通知送信
     */
    public function sendReservationChange(Reservation $reservation, array $changes): array
    {
        $customer = $reservation->customer;
        $store = $reservation->store;
        
        $changeText = $this->buildChangeText($changes);
        
        $message = "【予約変更のお知らせ】\n{$customer->last_name} {$customer->first_name}様\n\nご予約内容が変更されました。\n\n予約番号: {$reservation->reservation_number}\n{$changeText}\n\nご不明な点がございましたらお電話ください。\n{$store->phone}";
        
        return $this->sendNotification($customer, $store, $message, 'reservation_change');
    }

    /**
     * 予約キャンセル通知送信
     */
    public function sendReservationCancellation(Reservation $reservation): array
    {
        $customer = $reservation->customer;
        $store = $reservation->store;
        
        $date = Carbon::parse($reservation->reservation_date)->format('Y年n月j日');
        $time = Carbon::parse($reservation->start_time)->format('H:i');
        
        $message = "【予約キャンセル確認】\n{$customer->last_name} {$customer->first_name}様\n\n下記のご予約をキャンセルいたしました。\n\n予約番号: {$reservation->reservation_number}\n日時: {$date} {$time}〜\nメニュー: {$reservation->menu->name}\n\nまたのご利用をお待ちしております。\n{$store->name}\n{$store->phone}";
        
        return $this->sendNotification($customer, $store, $message, 'reservation_cancellation');
    }

    /**
     * フォローアップメッセージ送信
     */
    public function sendFollowUpMessage(Customer $customer, Store $store, int $daysSinceLastVisit): array
    {
        $message = $this->buildFollowUpMessage($customer, $store, $daysSinceLastVisit);
        return $this->sendNotification($customer, $store, $message, 'follow_up');
    }

    /**
     * LINE通知送信
     */
    private function sendLineNotification(Customer $customer, Store $store, string $message, string $type): bool
    {
        if (!$customer->line_user_id || !$store->line_enabled) {
            return false;
        }

        return $this->lineService->sendMessage($store, $customer->line_user_id, $message);
    }

    /**
     * SMS通知送信
     */
    private function sendSmsNotification(Customer $customer, string $message, string $type): bool
    {
        if (!$customer->phone) {
            return false;
        }

        return $this->smsService->sendSms($customer->phone, $message);
    }

    /**
     * 変更内容のテキスト生成
     */
    private function buildChangeText(array $changes): string
    {
        $changeLines = [];
        
        foreach ($changes as $field => $change) {
            switch ($field) {
                case 'reservation_date':
                    $changeLines[] = "日付: {$change['old']} → {$change['new']}";
                    break;
                case 'start_time':
                    $changeLines[] = "時間: {$change['old']} → {$change['new']}";
                    break;
                case 'menu':
                    $changeLines[] = "メニュー: {$change['old']} → {$change['new']}";
                    break;
                case 'total_amount':
                    $changeLines[] = "料金: ¥" . number_format($change['old']) . " → ¥" . number_format($change['new']);
                    break;
            }
        }
        
        return implode("\n", $changeLines);
    }

    /**
     * フォローアップメッセージ生成
     */
    private function buildFollowUpMessage(Customer $customer, Store $store, int $daysSinceLastVisit): string
    {
        $customerName = "{$customer->last_name} {$customer->first_name}様";
        
        if ($daysSinceLastVisit <= 7) {
            return "【{$store->name}】\n{$customerName}\n\n先日はご来店いただき、ありがとうございました！\n\n次回のご予約はお決まりでしょうか？\nお体の調子はいかがですか？\n\n何かご不明な点がございましたら、お気軽にお電話ください。\n\nまたのご来店をお待ちしております。";
        } elseif ($daysSinceLastVisit <= 14) {
            return "【{$store->name}】\n{$customerName}\n\nお疲れ様です！\nご来店から2週間が経ちましたが、お体の調子はいかがでしょうか？\n\n定期的なケアで効果を持続させませんか？\n次回のご予約をお待ちしております。\n\nご質問がございましたらお気軽にお電話ください。";
        } else {
            return "【{$store->name}】\n{$customerName}\n\nいつもありがとうございます。\nしばらくお見かけしませんが、お元気でしょうか？\n\n目の健康維持には定期的なケアが大切です。\nお時間のあるときに、ぜひお越しください。\n\n新しいメニューもご用意しております。\nお待ちしております！";
        }
    }

    /**
     * 通知設定確認
     */
    public function canSendNotification(Customer $customer, string $notificationType): array
    {
        $canSendLine = $customer->canReceiveLineNotifications();
        $canSendSms = $customer->phone && $customer->sms_notifications_enabled;
        
        return [
            'line' => $canSendLine,
            'sms' => $canSendSms,
            'any' => $canSendLine || $canSendSms
        ];
    }
}