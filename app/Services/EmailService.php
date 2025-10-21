<?php

namespace App\Services;

use Aws\Ses\SesClient;
use Illuminate\Support\Facades\Log;

class EmailService
{
    private ?SesClient $sesClient = null;

    public function __construct()
    {
        // AWS認証情報をconfigから取得
        $awsKey = config('services.ses.key');
        $awsSecret = config('services.ses.secret');
        $region = config('services.ses.region', 'ap-northeast-1');

        // AWS認証情報とリージョンがすべて揃っている場合のみSESクライアントを初期化
        if ($awsKey && $awsSecret && $region) {
            try {
                $this->sesClient = new SesClient([
                    'region' => $region,
                    'version' => 'latest',
                    'credentials' => [
                        'key' => $awsKey,
                        'secret' => $awsSecret,
                    ],
                ]);

                Log::info('EmailService: AWS SES initialized successfully', [
                    'region' => $region,
                ]);
            } catch (\Exception $e) {
                Log::error('EmailService: Failed to initialize AWS SES', [
                    'error' => $e->getMessage(),
                    'region' => $region,
                ]);
            }
        } else {
            Log::warning('EmailService: AWS認証情報が見つかりません', [
                'has_key' => !empty($awsKey),
                'has_secret' => !empty($awsSecret),
                'has_region' => !empty($region),
            ]);
        }
    }
    
    /**
     * メールを送信
     *
     * @param string $to 宛先メールアドレス
     * @param string $subject 件名
     * @param string $body 本文（HTML）
     * @param string|null $textBody 本文（テキスト）
     * @return bool
     */
    public function sendEmail(string $to, string $subject, string $body, ?string $textBody = null): bool
    {
        // AWS認証情報が設定されていない場合
        if (!$this->sesClient) {
            Log::warning('AWS SES認証情報が設定されていないため、メール送信をスキップ', [
                'to' => $to,
                'subject' => $subject,
            ]);

            // ローカル環境では成功扱い（開発を妨げない）
            if (config('app.env') === 'local' || config('app.env') === 'testing') {
                Log::info('開発環境のため、メール送信をスキップしました', [
                    'to' => $to,
                    'subject' => $subject,
                ]);
                return true;
            }

            return false;
        }

        try {
            $fromEmail = config('services.ses.from_email', 'noreply@meno-training.com');
            $fromName = config('services.ses.from_name', '目のトレーニング');

            // 本番環境でメール送信
            $result = $this->sesClient->sendEmail([
                'Source' => "$fromName <$fromEmail>",
                'Destination' => [
                    'ToAddresses' => [$to],
                ],
                'Message' => [
                    'Subject' => [
                        'Data' => $subject,
                        'Charset' => 'UTF-8',
                    ],
                    'Body' => [
                        'Html' => [
                            'Data' => $body,
                            'Charset' => 'UTF-8',
                        ],
                        'Text' => [
                            'Data' => $textBody ?? strip_tags($body),
                            'Charset' => 'UTF-8',
                        ],
                    ],
                ],
            ]);
            
            Log::info('メール送信成功', [
                'to' => $to,
                'messageId' => $result['MessageId'],
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('メール送信エラー', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    /**
     * OTPコードをメールで送信
     *
     * @param string $email メールアドレス
     * @param string $otp OTPコード
     * @return bool
     */
    public function sendOtpEmail(string $email, string $otp): bool
    {
        // 開発環境ではOTPコードをログに出力
        if (config('app.env') === 'local') {
            Log::info('📧 メール認証コード（開発環境用）', [
                'email' => $email,
                'otp' => $otp,
            ]);
        }

        $appName = config('app.name');
        
        $subject = "【{$appName}】認証コードのお知らせ";
        
        $body = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #059669, #10b981); padding: 30px; text-align: center; color: white; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-radius: 0 0 10px 10px; }
        .otp-code { font-size: 32px; font-weight: bold; text-align: center; padding: 20px; background: white; border: 2px solid #059669; border-radius: 8px; margin: 20px 0; letter-spacing: 8px; color: #059669; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #6b7280; }
        .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 10px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$appName}</h1>
            <p style="margin: 0;">認証コードのお知らせ</p>
        </div>
        <div class="content">
            <p>いつも{$appName}をご利用いただきありがとうございます。</p>
            <p>以下の認証コードを入力して、お手続きを続けてください：</p>
            
            <div class="otp-code">{$otp}</div>
            
            <p style="text-align: center; color: #ef4444;">有効期限：5分</p>
            
            <div class="warning">
                <strong>ご注意：</strong><br>
                このコードを他人に教えないでください。<br>
                スタッフがコードを聞くことはありません。
            </div>
            
            <p>このメールに心当たりがない場合は、無視していただいて構いません。</p>
        </div>
        <div class="footer">
            <p>&copy; 2025 {$appName}. All rights reserved.</p>
            <p>このメールは自動送信されています。返信はできません。</p>
        </div>
    </div>
</body>
</html>
HTML;

        $textBody = <<<TEXT
【{$appName}】認証コードのお知らせ

いつも{$appName}をご利用いただきありがとうございます。

認証コード: {$otp}
有効期限: 5分

このコードを他人に教えないでください。
スタッフがコードを聞くことはありません。

このメールに心当たりがない場合は、無視していただいて構いません。

---
このメールは自動送信されています。返信はできません。
© 2025 {$appName}. All rights reserved.
TEXT;
        
        return $this->sendEmail($email, $subject, $body, $textBody);
    }
}