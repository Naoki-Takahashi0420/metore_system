<?php

namespace App\Services;

use Aws\Sns\SnsClient;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private ?SnsClient $snsClient = null;
    
    public function __construct()
    {
        // AWS認証情報をconfigから取得（本番環境対応）
        $awsKey = config('services.sns.key');
        $awsSecret = config('services.sns.secret');
        
        // AWS認証情報がある場合のみSNSクライアントを初期化
        if ($awsKey && $awsSecret) {
            $this->snsClient = new SnsClient([
                'region' => config('services.sns.region', 'ap-northeast-1'),
                'version' => 'latest',
                'credentials' => [
                    'key' => $awsKey,
                    'secret' => $awsSecret,
                ],
            ]);
        } else {
            Log::warning('SmsService: AWS認証情報が見つかりません', [
                'config_key' => config('services.sns.key'),
                'config_secret_exists' => !empty(config('services.sns.secret')),
            ]);
        }
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
        // AWS認証情報が設定されていない場合
        if (!$this->snsClient) {
            Log::warning('AWS SNS認証情報が設定されていないため、SMS送信をスキップ', [
                'phone' => $phone,
            ]);
            return false;
        }
        
        try {
            // 電話番号をE.164形式に変換
            $phone = $this->formatPhoneNumber($phone);
            
            // 本番環境でSMS送信
            $result = $this->snsClient->publish([
                'Message' => $message,
                'PhoneNumber' => $phone,
                'MessageAttributes' => [
                    'AWS.SNS.SMS.SenderID' => [
                        'DataType' => 'String',
                        'StringValue' => config('services.sns.sender_id', 'METORE'),
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
        // テスト環境の場合は実際にSMS送信せず成功扱い
        if (config('app.env') === 'local' || config('app.env') === 'testing') {
            Log::info('テスト環境のため、SMS送信をスキップ', [
                'phone' => $phone,
                'otp' => $otp,
                'env' => config('app.env'),
            ]);
            return true;
        }
        
        // AWS認証情報がない場合
        if (!$this->snsClient) {
            Log::error('AWS SNS認証情報が設定されていません', [
                'phone' => $phone,
                'key_exists' => config('services.sns.key') !== null,
                'secret_exists' => config('services.sns.secret') !== null,
            ]);
            return false;
        }
        
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