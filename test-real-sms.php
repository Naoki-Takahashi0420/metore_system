<?php
require_once 'vendor/autoload.php';

use App\Services\OtpService;

// Laravelアプリケーションのブートストラップ
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== 実際のSMS送信テスト ===\n\n";

// 環境情報
echo "APP_ENV: " . config('app.env') . "\n";
echo "AWS_ACCESS_KEY: " . (config('services.sns.key') ? 'Set' : 'Not Set') . "\n";
echo "AWS_SECRET: " . (config('services.sns.secret') ? 'Set' : 'Not Set') . "\n";
echo "SMS_ENABLED: " . (config('services.sns.enabled') ? 'true' : 'false') . "\n";

// OTP送信テスト
echo "\n=== OTP送信テスト ===\n";
try {
    $otpService = app(OtpService::class);
    echo "OtpService created successfully\n";

    $targetPhone = '08033372305';
    echo "Target phone: $targetPhone\n";

    $result = $otpService->sendOtp($targetPhone);
    echo "Send result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";

    // データベースでOTPが作成されているか確認
    $latestOtp = \App\Models\OtpVerification::where('phone', $targetPhone)
        ->latest()
        ->first();

    if ($latestOtp) {
        echo "Database OTP created:\n";
        echo "  Phone: {$latestOtp->phone}\n";
        echo "  Code: {$latestOtp->otp_code}\n";
        echo "  Created: {$latestOtp->created_at}\n";
        echo "  Expires: {$latestOtp->expires_at}\n";
    } else {
        echo "No OTP record found in database\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}