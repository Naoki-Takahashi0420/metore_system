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
        $region = config('services.sns.region', 'ap-northeast-1');

        // AWS認証情報とリージョンがすべて揃っている場合のみSNSクライアントを初期化
        if ($awsKey && $awsSecret && $region) {
            try {
                $this->snsClient = new SnsClient([
                    'region' => $region,
                    'version' => 'latest',
                    'credentials' => [
                        'key' => $awsKey,
                        'secret' => $awsSecret,
                    ],
                ]);

                Log::info('SmsService: AWS SNS initialized successfully', [
                    'region' => $region,
                ]);
            } catch (\Exception $e) {
                Log::error('SmsService: Failed to initialize AWS SNS', [
                    'error' => $e->getMessage(),
                    'region' => $region,
                ]);
            }
        } else {
            Log::warning('SmsService: AWS認証情報が見つかりません', [
                'has_key' => !empty($awsKey),
                'has_secret' => !empty($awsSecret),
                'has_region' => !empty($region),
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
        // ローカル環境では送信しない
        if (config('app.env') === 'local') {
            Log::info('[LOCAL] SMS送信をスキップ（ローカル環境）', [
                'phone' => $phone,
                'message_length' => strlen($message),
            ]);
            return true;
        }

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
            $messageAttributes = [
                'AWS.SNS.SMS.SMSType' => [
                    'DataType' => 'String',
                    'StringValue' => 'Transactional',
                ],
            ];

            // Sender IDを設定（AWS SNS側でDefaultSenderID: METOREが設定済み）
            // 注意: 日本のキャリアはSender IDをサポートしていないため、
            // キャリアによっては「NOTICE」や数字に置き換えられる場合があります
            $senderId = config('services.sns.sender_id');
            if ($senderId && $senderId !== 'NONE') {
                $messageAttributes['AWS.SNS.SMS.SenderID'] = [
                    'DataType' => 'String',
                    'StringValue' => $senderId,
                ];
            }

            Log::info('📤 [SMS] AWS SNS publish準備', [
                'phone' => $phone,
                'sender_id' => $senderId,
                'has_sender_id_attribute' => isset($messageAttributes['AWS.SNS.SMS.SenderID']),
                'message_attributes' => $messageAttributes,
            ]);

            $result = $this->snsClient->publish([
                'Message' => $message,
                'PhoneNumber' => $phone,
                'MessageAttributes' => $messageAttributes,
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
        Log::info('🚀 [SMS] sendOtp called', [
            'phone' => $phone,
            'otp' => $otp,
            'env' => config('app.env'),
            'sns_enabled' => config('services.sns.enabled', true),
            'has_sns_client' => $this->snsClient !== null,
        ]);

        // 開発環境ではOTPコードをログに出力
        if (config('app.env') === 'local') {
            Log::info('📱 SMS認証コード（開発環境用）', [
                'phone' => $phone,
                'otp' => $otp,
            ]);
        }

        // SMS送信が無効化されている場合はここでリターン
        if (!config('services.sns.enabled', true)) {
            Log::info('⚠️ [SMS] SMS送信が無効化されています', [
                'phone' => $phone,
                'otp' => $otp,
            ]);
            return true;
        }

        // AWS認証情報がない場合
        if (!$this->snsClient) {
            // ローカル環境では警告、本番環境ではエラー
            $logLevel = (config('app.env') === 'production') ? 'error' : 'warning';
            Log::$logLevel('❌ [SMS] AWS SNS認証情報が設定されていません', [
                'phone' => $phone,
                'env' => config('app.env'),
                'key_exists' => config('services.sns.key') !== null,
                'secret_exists' => config('services.sns.secret') !== null,
            ]);

            // ローカル環境では成功扱い（開発を妨げない）
            if (config('app.env') === 'local' || config('app.env') === 'testing') {
                Log::info('開発環境のため、SMS送信をスキップしました', [
                    'phone' => $phone,
                    'otp' => $otp,
                ]);
                return true;
            }

            return false;
        }

        $message = sprintf(
            "【%s】認証コード: %s\n有効期限: 5分",
            config('app.name'),
            $otp
        );

        Log::info('📤 [SMS] sendSms呼び出し', [
            'phone' => $phone,
            'message_length' => strlen($message),
        ]);

        $result = $this->sendSms($phone, $message);

        Log::info('✅ [SMS] sendSms結果', [
            'phone' => $phone,
            'result' => $result,
        ]);

        return $result;
    }
    
    /**
     * 電話番号をE.164形式に変換
     *
     * @param string $phone
     * @return string
     */
    private function formatPhoneNumber(string $phone): string
    {
        // ハイフン、スペース、その他の記号を除去
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // すでに+81で始まる場合はそのまま
        if (strpos($phone, '+81') === 0) {
            return $phone;
        }

        // 日本の携帯電話番号（080, 090, 070）の正しいE.164変換
        if (preg_match('/^0[789]0\d{8}$/', $phone)) {
            // 最初の0を削除して+81を追加
            // 例: 08033372305 → +818033372305 (正しい)
            return '+81' . substr($phone, 1);
        }

        // その他の0から始まる番号
        if (strpos($phone, '0') === 0) {
            $phone = '+81' . substr($phone, 1);
            return $phone;
        }

        // 81で始まる場合
        if (strpos($phone, '81') === 0) {
            $phone = '+' . $phone;
            return $phone;
        }

        // それ以外（80, 90などで始まる場合）
        if (preg_match('/^[789]0/', $phone)) {
            $phone = '+81' . $phone;
            return $phone;
        }

        // +がない場合は追加
        if (strpos($phone, '+') !== 0) {
            $phone = '+' . $phone;
        }

        return $phone;
    }
}