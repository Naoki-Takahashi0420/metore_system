<?php

namespace App\Services;

use Aws\Sns\SnsClient;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private SnsClient $snsClient;
    
    public function __construct()
    {
        $this->snsClient = new SnsClient([
            'region' => config('services.sns.region', 'ap-northeast-1'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('services.sns.key'),
                'secret' => config('services.sns.secret'),
            ],
        ]);
    }
    
    /**
     * SMSを送信
     *
     * @param string $phone 電話番号（E.164形式）
     * @param string $message メッセージ
     * @return bool
     */
    public function sendSms(string $phone, string $message): bool
    {
        try {
            // 電話番号をE.164形式に変換
            $phone = $this->formatPhoneNumber($phone);
            
            // 一時的に全環境でログ出力のみ（サンドボックス承認待ち）
            // TODO: サンドボックス承認後、本番環境で実際のSMS送信を有効化
            Log::info('SMS送信（サンドボックス承認待ち）', [
                'phone' => $phone,
                'message' => $message,
                'environment' => app()->environment(),
            ]);
            return true;
            
            // 本番環境でSMS送信（サンドボックス承認後に有効化）
            /*
            $result = $this->snsClient->publish([
                'Message' => $message,
                'PhoneNumber' => $phone,
                'MessageAttributes' => [
                    'AWS.SNS.SMS.SenderID' => [
                        'DataType' => 'String',
                        'StringValue' => config('services.sns.sender_id', 'Xsyumeno'),
                    ],
                    'AWS.SNS.SMS.SMSType' => [
                        'DataType' => 'String',
                        'StringValue' => 'Transactional',
                    ],
                ],
            ]);
            
            Log::info('SMS送信成功', [
                'phone' => $phone,
                'messageId' => $result['MessageId'],
            ]);
            
            return true;
            */
            
        } catch (\Exception $e) {
            Log::error('SMS送信エラー', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    /**
     * OTPコードを送信
     *
     * @param string $phone 電話番号
     * @param string $otp OTPコード
     * @return bool
     */
    public function sendOtp(string $phone, string $otp): bool
    {
        $message = sprintf(
            "【%s】認証コード: %s\n有効期限: 5分",
            config('app.name'),
            $otp
        );
        
        return $this->sendSms($phone, $message);
    }
    
    /**
     * 電話番号をE.164形式に変換
     *
     * @param string $phone
     * @return string
     */
    private function formatPhoneNumber(string $phone): string
    {
        // ハイフン、スペースを除去
        $phone = preg_replace('/[\s\-]/', '', $phone);
        
        // 0から始まる場合は+81に変換
        if (strpos($phone, '0') === 0) {
            $phone = '+81' . substr($phone, 1);
        }
        
        // +がない場合は追加
        if (strpos($phone, '+') !== 0) {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }
}