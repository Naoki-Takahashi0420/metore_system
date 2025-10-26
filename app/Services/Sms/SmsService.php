<?php

namespace App\Services\Sms;

use Aws\Sns\SnsClient;
use App\Models\Customer;
use App\Models\MedicalRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private $snsClient;
    private $senderId;
    
    public function __construct()
    {
        // AWS SNS設定を環境変数から取得
        $key = config('services.sns.key');
        $secret = config('services.sns.secret');
        $region = config('services.sns.region', 'ap-northeast-1');
        $this->senderId = config('services.sns.sender_id', 'Xsyumeno');
        
        if ($key && $secret) {
            $this->snsClient = new SnsClient([
                'version' => 'latest',
                'region' => $region,
                'credentials' => [
                    'key' => $key,
                    'secret' => $secret,
                ],
            ]);
        }
    }
    
    /**
     * SMS送信
     */
    public function sendSms(string $to, string $message): bool
    {
        // ローカル環境では送信しない
        if (config('app.env') === 'local') {
            Log::info('[LOCAL] SMS送信をスキップ（ローカル環境）', [
                'to' => $to,
                'message_length' => strlen($message),
            ]);
            return true;
        }

        try {
            // 日本の電話番号形式に変換（0901234567 → +81901234567）
            $to = $this->formatPhoneNumber($to);

            if (!$this->snsClient) {
                Log::warning('AWS SNS client not configured. SMS not sent.');
                return false;
            }
            
            // AWS SNSでSMS送信
            $result = $this->snsClient->publish([
                'Message' => $message,
                'PhoneNumber' => $to,
                'MessageAttributes' => [
                    'AWS.SNS.SMS.SenderID' => [
                        'DataType' => 'String',
                        'StringValue' => $this->senderId,
                    ],
                    'AWS.SNS.SMS.SMSType' => [
                        'DataType' => 'String',
                        'StringValue' => 'Transactional', // 重要なメッセージとして送信
                    ],
                ],
            ]);
            
            Log::info('SMS sent successfully via AWS SNS', [
                'to' => $to,
                'messageId' => $result['MessageId']
            ]);
            return true;
            
        } catch (\Exception $e) {
            Log::error('SMS sending failed', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 予約リマインダーSMS送信
     */
    public function sendReservationReminder(Customer $customer, MedicalRecord $medicalRecord): bool
    {
        if (!$customer->phone) {
            Log::warning('Customer has no phone number', ['customer_id' => $customer->id]);
            return false;
        }
        
        $nextVisitDate = Carbon::parse($medicalRecord->next_visit_date);
        $storeName = config('app.name', '美容サロン');
        
        $message = $this->buildReminderMessage($customer, $nextVisitDate, $storeName);
        
        $success = $this->sendSms($customer->phone, $message);
        
        if ($success) {
            // リマインダー送信日時を記録
            $medicalRecord->update([
                'reminder_sent_at' => now()
            ]);
        }
        
        return $success;
    }
    
    /**
     * リマインダーメッセージ作成
     */
    private function buildReminderMessage(Customer $customer, Carbon $nextVisitDate, string $storeName): string
    {
        $customerName = $customer->last_name . '様';
        $dateStr = $nextVisitDate->format('m月d日');
        
        // メッセージテンプレート
        $message = "【{$storeName}】\n";
        $message .= "{$customerName}\n";
        $message .= "いつもご利用ありがとうございます。\n";
        $message .= "前回お伝えした次回メンテナンス推奨日（{$dateStr}頃）が近づいてまいりました。\n";
        $message .= "ご予約はお電話または公式サイトから承っております。\n";
        $message .= "お待ちしております。";
        
        return $message;
    }
    
    /**
     * 予約確認SMS送信
     */
    public function sendReservationConfirmation($reservation): bool
    {
        $customer = $reservation->customer;
        if (!$customer || !$customer->phone) {
            return false;
        }
        
        $storeName = $reservation->store->name ?? config('app.name');
        $dateStr = Carbon::parse($reservation->reservation_date)->format('m月d日');
        $timeStr = Carbon::parse($reservation->start_time)->format('H:i');
        
        $message = "【{$storeName}】\n";
        $message .= "{$customer->last_name}様\n";
        $message .= "ご予約を承りました。\n";
        $message .= "日時: {$dateStr} {$timeStr}\n";
        $message .= "メニュー: {$reservation->menu->name}\n";
        $message .= "ご来店をお待ちしております。";
        
        return $this->sendSms($customer->phone, $message);
    }
    
    /**
     * 電話番号を国際形式に変換
     */
    private function formatPhoneNumber(string $phone): string
    {
        // 全角数字を半角に変換
        $phone = mb_convert_kana($phone, 'n');
        
        // 数字以外を除去
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // 日本の電話番号の場合、+81を付ける
        if (substr($phone, 0, 1) === '0') {
            $phone = '+81' . substr($phone, 1);
        } elseif (substr($phone, 0, 2) !== '81') {
            $phone = '+81' . $phone;
        } elseif (substr($phone, 0, 2) === '81') {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }
}