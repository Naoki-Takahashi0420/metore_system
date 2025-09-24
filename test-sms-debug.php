<?php
require_once 'vendor/autoload.php';

use App\Services\SmsService;
use App\Services\OtpService;

// Laravelアプリケーションのブートストラップ
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== SMS送信デバッグテスト ===\n\n";

// 環境情報
echo "APP_ENV: " . config('app.env') . "\n";
echo "SMS_ENABLED: " . config('services.sns.enabled') . "\n";
echo "AWS_ACCESS_KEY_ID exists: " . (config('services.sns.key') ? 'Yes' : 'No') . "\n";
echo "AWS_SECRET_ACCESS_KEY exists: " . (config('services.sns.secret') ? 'Yes' : 'No') . "\n";

// SmsServiceのテスト
echo "\n=== SmsServiceテスト ===\n";
$smsService = new SmsService();

// プライベートメソッドのテストのためにリフレクションを使用
$reflection = new ReflectionClass($smsService);
$formatMethod = $reflection->getMethod('formatPhoneNumber');
$formatMethod->setAccessible(true);

$testPhone = '08033372305';
$formattedPhone = $formatMethod->invoke($smsService, $testPhone);
echo "Original: $testPhone\n";
echo "Formatted: $formattedPhone\n";

// 実際のOTP送信テスト
echo "\n=== OTP送信テスト ===\n";
$otpService = app(OtpService::class);
$result = $otpService->sendOtp('08033372305');
echo "OTP送信結果: " . ($result ? 'Success' : 'Failed') . "\n";

echo "\n=== 直接SMS送信テスト ===\n";
$message = "テストメッセージ: 認証コード 123456";
$directResult = $smsService->sendOtp('08033372305', '123456');
echo "直接SMS送信結果: " . ($directResult ? 'Success' : 'Failed') . "\n";