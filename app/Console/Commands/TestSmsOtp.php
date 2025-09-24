<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SmsService;
use App\Services\OtpService;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;

class TestSmsOtp extends Command
{
    protected $signature = 'sms:test
                            {phone : 送信先電話番号}
                            {--real : 実際にSMSを送信する（デフォルトはテストモード）}
                            {--verify= : OTPコードを検証する}';

    protected $description = 'SMS OTP送信機能をテスト';

    private SmsService $smsService;
    private OtpService $otpService;

    public function __construct(SmsService $smsService, OtpService $otpService)
    {
        parent::__construct();
        $this->smsService = $smsService;
        $this->otpService = $otpService;
    }

    public function handle()
    {
        $phone = $this->argument('phone');
        $realMode = $this->option('real');
        $verifyCode = $this->option('verify');

        $this->info('========================================');
        $this->info('SMS OTP テスト');
        $this->info('========================================');
        $this->info("電話番号: {$phone}");
        $this->info("モード: " . ($realMode ? '本番送信' : 'テストモード'));
        $this->info("環境: " . config('app.env'));
        $this->info("");

        // 検証モード
        if ($verifyCode) {
            $this->verifyOtp($phone, $verifyCode);
            return Command::SUCCESS;
        }

        // 設定確認
        $this->checkConfiguration();

        // テストモード設定
        if (!$realMode) {
            config(['services.sns.use_test_otp' => true]);
            config(['services.sns.enabled' => false]);
            $this->warn('⚠️  テストモード: SMS送信はスキップされます');
        }

        // OTP送信テスト
        $this->testOtpSending($phone);

        return Command::SUCCESS;
    }

    private function checkConfiguration()
    {
        $this->info('設定確認:');
        $this->table(
            ['項目', '値'],
            [
                ['SMS有効化', config('services.sns.enabled') ? '✓ 有効' : '✗ 無効'],
                ['AWS Key', config('services.sns.key') ? '✓ 設定済' : '✗ 未設定'],
                ['AWS Secret', config('services.sns.secret') ? '✓ 設定済' : '✗ 未設定'],
                ['リージョン', config('services.sns.region', '未設定')],
                ['送信元ID', config('services.sns.sender_id', '未設定')],
                ['テストOTP', config('services.sns.use_test_otp') ? '有効 (123456)' : '無効'],
            ]
        );
        $this->info("");
    }

    private function testOtpSending($phone)
    {
        $this->info('OTP送信テスト開始...');

        try {
            // OTP送信
            $result = $this->otpService->sendOtp($phone);

            if ($result) {
                $this->info('✓ OTP送信成功');

                // テストモードの場合はOTPコードを表示
                if (config('services.sns.use_test_otp')) {
                    $this->info('');
                    $this->warn('テストOTPコード: 123456');
                    $this->info('検証コマンド: php artisan sms:test ' . $phone . ' --verify=123456');
                }
            } else {
                $this->error('✗ OTP送信失敗');
            }

            // ログ確認
            $this->checkLogs();

        } catch (\Exception $e) {
            $this->error('エラーが発生しました: ' . $e->getMessage());
            Log::error('SMS OTPテストエラー', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function verifyOtp($phone, $code)
    {
        $this->info("OTP検証: {$code}");

        $result = $this->otpService->verifyOtp($phone, $code);

        if ($result) {
            $this->info('✓ OTP検証成功');
        } else {
            $this->error('✗ OTP検証失敗');
            $this->warn('考えられる原因:');
            $this->warn('- OTPコードが間違っている');
            $this->warn('- OTPが期限切れ（5分経過）');
            $this->warn('- すでに使用済みのOTP');
        }
    }

    private function checkLogs()
    {
        $this->info('');
        $this->info('最新ログ確認:');

        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            // ファイルサイズが大きい場合は最後の部分だけ読む
            $handle = fopen($logFile, 'r');
            if ($handle) {
                // ファイルの最後から10KB読む
                fseek($handle, -10240, SEEK_END);
                $content = fread($handle, 10240);
                fclose($handle);

                $lines = explode("\n", $content);
                $smsLogs = array_filter($lines, function($line) {
                    return strpos($line, 'SMS') !== false || strpos($line, 'OTP') !== false;
                });

                if (!empty($smsLogs)) {
                    // 最新5件のみ表示
                    $recentLogs = array_slice($smsLogs, -5);
                    foreach ($recentLogs as $log) {
                        $trimmed = trim($log);
                        if (!empty($trimmed)) {
                            $this->line($trimmed);
                        }
                    }
                } else {
                    $this->info('SMS関連のログはありません');
                }
            }
        }
    }
}