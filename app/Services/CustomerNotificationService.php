<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Store;
use App\Models\Reservation;
use App\Models\NotificationLog;
use App\Services\SimpleLineService;
use App\Services\SmsService;
use App\Services\EmailService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CustomerNotificationService
{
    private SimpleLineService $lineService;
    private SmsService $smsService;
    private EmailService $emailService;

    public function __construct(SimpleLineService $lineService, SmsService $smsService, EmailService $emailService)
    {
        $this->lineService = $lineService;
        $this->smsService = $smsService;
        $this->emailService = $emailService;
    }

    /**
     * 顧客に通知を送信（LINE優先、メール代替、SMSフォールバック）
     * ※SMSはコスト高のため最終手段
     */
    public function sendNotification(
        Customer $customer,
        Store $store,
        string $message,
        string $notificationType = 'general',
        ?int $reservationId = null
    ): array {
        $results = [];

        // Idempotency-key生成（重複防止）
        $idempotencyKey = NotificationLog::generateIdempotencyKey(
            $notificationType,
            $reservationId,
            $customer->id,
            null
        );

        // 重複チェック（過去10分以内に同じ通知を送信していないか）
        if (NotificationLog::isDuplicate($idempotencyKey)) {
            Log::warning('⚠️ 重複通知を検出、送信をスキップ', [
                'idempotency_key' => $idempotencyKey,
                'customer_id' => $customer->id,
                'type' => $notificationType
            ]);

            return [
                'line' => false,
                'sms' => false,
                'email' => false,
                'skipped' => true,
                'reason' => 'duplicate'
            ];
        }

        // デバッグログ追加
        Log::info('🔔 顧客通知送信開始', [
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'type' => $notificationType,
            'has_line' => $customer->canReceiveLineNotifications(),
            'has_phone' => !empty($customer->phone),
            'sms_enabled' => $customer->sms_notifications_enabled,
            'has_email' => !empty($customer->email)
        ]);

        // 優先順位: LINE → メール → SMS（SMSはコスト高のため最終手段）

        // 1. LINE連携済みの場合はLINEを試行
        if ($customer->canReceiveLineNotifications()) {
            $lineResult = $this->sendLineNotification(
                $customer,
                $store,
                $message,
                $notificationType,
                $reservationId,
                $idempotencyKey
            );
            $results['line'] = $lineResult;

            if ($lineResult) {
                Log::info('✅ 顧客通知成功 (LINE) - メール/SMS送信をスキップ', [
                    'customer_id' => $customer->id,
                    'store_id' => $store->id,
                    'type' => $notificationType
                ]);
                // LINE送信成功時は他を送信しない
                $results['email'] = false;
                $results['sms'] = false;
                return $results;
            }

            Log::warning('⚠️ LINE通知失敗、メールにフォールバック', [
                'customer_id' => $customer->id,
                'store_id' => $store->id
            ]);
        }

        // 2. LINE送信失敗またはLINE未連携の場合はメールを試行
        if ($customer->email) {
            Log::info('📧 メール送信を試行', [
                'customer_id' => $customer->id,
                'email' => $customer->email
            ]);
            $emailResult = $this->sendEmailNotification(
                $customer,
                $store,
                $message,
                $notificationType,
                $reservationId,
                $idempotencyKey
            );
            $results['email'] = $emailResult;

            if ($emailResult) {
                Log::info('✅ 顧客通知成功 (メール) - SMS送信をスキップ', [
                    'customer_id' => $customer->id,
                    'store_id' => $store->id,
                    'type' => $notificationType
                ]);
                $results['sms'] = false;
                return $results;
            }

            Log::warning('⚠️ メール通知失敗、SMSにフォールバック', [
                'customer_id' => $customer->id,
                'store_id' => $store->id
            ]);
        } else {
            Log::warning('メール通知スキップ (メールアドレスなし)', [
                'customer_id' => $customer->id
            ]);
            $results['email'] = false;
        }

        // 3. メール送信失敗またはメール利用不可の場合はSMSを試行（最終手段）
        if ($customer->phone && $customer->sms_notifications_enabled) {
            Log::info('📱 SMS送信を試行（フォールバック）', [
                'customer_id' => $customer->id,
                'phone' => $customer->phone
            ]);
            $smsResult = $this->sendSmsNotification(
                $customer,
                $store,
                $message,
                $notificationType,
                $reservationId,
                $idempotencyKey
            );
            $results['sms'] = $smsResult;

            if ($smsResult) {
                Log::info('✅ 顧客通知成功 (SMS)', [
                    'customer_id' => $customer->id,
                    'store_id' => $store->id,
                    'type' => $notificationType
                ]);
            } else {
                Log::error('❌ SMS通知も失敗 - 全通知手段が失敗', [
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

        // 店舗カスタムメッセージがあれば使用、なければデフォルト
        if (!empty($store->line_reminder_message)) {
            $message = $this->replaceTemplateVariables($store->line_reminder_message, $reservation);
        } else {
            $message = "【予約リマインダー】\n{$customer->last_name} {$customer->first_name}様\n\n明日のご予約をお忘れなく！\n\n店舗: {$store->name}\n日時: {$date} {$time}〜\nメニュー: {$reservation->menu->name}\n\nご質問がございましたらお気軽にお電話ください。\n{$store->phone}";
        }

        return $this->sendNotification($customer, $store, $message, 'reservation_reminder', $reservation->id);
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

        return $this->sendNotification($customer, $store, $message, 'reservation_confirmation', $reservation->id);
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

        return $this->sendNotification($customer, $store, $message, 'reservation_change', $reservation->id);
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

        return $this->sendNotification($customer, $store, $message, 'reservation_cancellation', $reservation->id);
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
     * LINE通知送信（履歴記録付き）
     */
    private function sendLineNotification(
        Customer $customer,
        Store $store,
        string $message,
        string $type,
        ?int $reservationId,
        string $idempotencyKey
    ): bool {
        if (!$customer->line_user_id || !$store->line_enabled) {
            return false;
        }

        // 通知ログを作成（pending状態）
        $notificationLog = NotificationLog::create([
            'reservation_id' => $reservationId,
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'notification_type' => $type,
            'channel' => 'line',
            'status' => 'pending',
            'recipient' => 'LINE:' . $customer->line_user_id,
            'idempotency_key' => $idempotencyKey . ':line',
            'metadata' => [
                'line_user_id' => $customer->line_user_id,
                'store_name' => $store->name,
            ],
        ]);

        try {
            $result = $this->lineService->sendMessage($store, $customer->line_user_id, $message);

            if ($result) {
                $notificationLog->markAsSent(null, ['sent_via' => 'SimpleLineService']);
                return true;
            } else {
                $notificationLog->markAsFailed('line_send_failed', 'LINE送信失敗');
                return false;
            }
        } catch (\Exception $e) {
            $notificationLog->markAsFailed(
                'line_exception',
                $e->getMessage(),
                ['exception_class' => get_class($e)]
            );
            Log::error('LINE送信例外', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * SMS通知送信（履歴記録付き）
     */
    private function sendSmsNotification(
        Customer $customer,
        Store $store,
        string $message,
        string $type,
        ?int $reservationId,
        string $idempotencyKey
    ): bool {
        if (!$customer->phone) {
            return false;
        }

        // 通知ログを作成（pending状態）
        $notificationLog = NotificationLog::create([
            'reservation_id' => $reservationId,
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'notification_type' => $type,
            'channel' => 'sms',
            'status' => 'pending',
            'recipient' => $customer->phone,
            'idempotency_key' => $idempotencyKey . ':sms',
            'metadata' => [
                'store_name' => $store->name,
                'message_length' => mb_strlen($message),
            ],
        ]);

        try {
            $result = $this->smsService->sendSms($customer->phone, $message);

            if ($result) {
                $notificationLog->markAsSent(null, ['sent_via' => 'SmsService']);
                return true;
            } else {
                $notificationLog->markAsFailed('sms_send_failed', 'SMS送信失敗');
                return false;
            }
        } catch (\Exception $e) {
            $notificationLog->markAsFailed(
                'sms_exception',
                $e->getMessage(),
                ['exception_class' => get_class($e)]
            );
            Log::error('SMS送信例外', [
                'customer_id' => $customer->id,
                'phone' => $customer->phone,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * メール通知送信（履歴記録付き）
     */
    private function sendEmailNotification(
        Customer $customer,
        Store $store,
        string $message,
        string $type,
        ?int $reservationId,
        string $idempotencyKey
    ): bool {
        if (!$customer->email) {
            return false;
        }

        $subject = $this->getEmailSubject($type);

        // 通知ログを作成（pending状態）
        $notificationLog = NotificationLog::create([
            'reservation_id' => $reservationId,
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'notification_type' => $type,
            'channel' => 'email',
            'status' => 'pending',
            'recipient' => $customer->email,
            'idempotency_key' => $idempotencyKey . ':email',
            'metadata' => [
                'subject' => $subject,
                'store_name' => $store->name,
                'message_length' => mb_strlen($message),
            ],
        ]);

        // HTMLメール用のフォーマット
        $htmlMessage = nl2br(htmlspecialchars($message));
        $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #059669, #10b981); padding: 20px; text-align: center; color: white; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; border-radius: 0 0 10px 10px; }
        .message { white-space: pre-wrap; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>{$store->name}</h2>
            <p style="margin: 0;">{$subject}</p>
        </div>
        <div class="content">
            <div class="message">{$htmlMessage}</div>
        </div>
        <div class="footer">
            <p>&copy; 2025 {$store->name}. All rights reserved.</p>
            <p>このメールは自動送信されています。</p>
        </div>
    </div>
</body>
</html>
HTML;

        try {
            $result = $this->emailService->sendEmail($customer->email, $subject, $htmlBody, $message);

            if ($result) {
                $notificationLog->markAsSent(null, [
                    'sent_via' => 'EmailService',
                    'subject' => $subject
                ]);
                return true;
            } else {
                $notificationLog->markAsFailed('email_send_failed', 'メール送信失敗');
                return false;
            }
        } catch (\Exception $e) {
            $notificationLog->markAsFailed(
                'email_exception',
                $e->getMessage(),
                ['exception_class' => get_class($e)]
            );
            Log::error('メール送信例外', [
                'customer_id' => $customer->id,
                'email' => $customer->email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * メール件名の取得
     */
    private function getEmailSubject(string $type): string
    {
        return match($type) {
            'reservation_confirmation' => '予約確認',
            'reservation_change' => '予約変更のお知らせ',
            'reservation_cancellation' => '予約キャンセル確認',
            'reservation_reminder' => '予約リマインダー',
            'follow_up' => 'フォローアップメッセージ',
            default => '通知',
        };
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
        $canSendEmail = !empty($customer->email);

        return [
            'line' => $canSendLine,
            'sms' => $canSendSms,
            'email' => $canSendEmail,
            'any' => $canSendLine || $canSendSms || $canSendEmail
        ];
    }

    /**
     * テンプレート変数を置換
     */
    private function replaceTemplateVariables(string $template, Reservation $reservation): string
    {
        $customer = $reservation->customer;
        $store = $reservation->store;
        $menu = $reservation->menu;

        $date = Carbon::parse($reservation->reservation_date)->format('Y年n月j日');
        $time = Carbon::parse($reservation->start_time)->format('H:i');

        $replacements = [
            '{{customer_name}}' => "{$customer->last_name} {$customer->first_name}",
            '{{customer_last_name}}' => $customer->last_name,
            '{{customer_first_name}}' => $customer->first_name,
            '{{store_name}}' => $store->name,
            '{{store_phone}}' => $store->phone ?? '',
            '{{reservation_date}}' => $date,
            '{{reservation_time}}' => $time,
            '{{menu_name}}' => $menu->name ?? '',
            '{{reservation_number}}' => $reservation->reservation_number,
            '{{total_amount}}' => number_format($reservation->total_amount),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}