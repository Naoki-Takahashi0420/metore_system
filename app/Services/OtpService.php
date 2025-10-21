<?php

namespace App\Services;

use App\Models\OtpVerification;
use App\Helpers\PhoneHelper;
use Carbon\Carbon;
use Illuminate\Support\Str;

class OtpService
{
    private SmsService $smsService;
    private EmailService $emailService;

    public function __construct(SmsService $smsService, EmailService $emailService)
    {
        $this->smsService = $smsService;
        $this->emailService = $emailService;
    }
    
    /**
     * OTPを生成して送信
     *
     * @param string $phone
     * @param string|null $email オプション：メールアドレスが指定された場合はメールでも送信
     * @return array ['success' => bool, 'sms_sent' => bool, 'email_sent' => bool]
     */
    public function sendOtp(string $phone, ?string $email = null): array
    {
        // 電話番号を正規化
        $normalizedPhone = PhoneHelper::normalize($phone);

        // 既存の未使用OTPを無効化（古いものだけ削除）
        // 1分以内のものは残す（レート制限の判定用）
        OtpVerification::where('phone', $normalizedPhone)
            ->whereNull('verified_at')
            ->where('created_at', '<', Carbon::now()->subMinutes(1))
            ->delete();

        // 新しいOTPを生成
        $otp = $this->generateOtp();

        \Log::info('🔑 [OTP] OTP生成', [
            'phone' => $phone,
            'normalized_phone' => $normalizedPhone,
            'otp' => $otp,
            'email' => $email,
            'timestamp' => now()->toIso8601String(),
        ]);

        // データベースに保存（正規化した電話番号で保存）
        OtpVerification::create([
            'phone' => $normalizedPhone,
            'otp_code' => $otp,
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        // SMS送信（元の電話番号形式で送信）
        \Log::info('📱 [OTP] SMS送信開始', [
            'phone' => $phone,
            'otp' => $otp,
        ]);
        $smsSent = $this->smsService->sendOtp($phone, $otp);
        \Log::info('📱 [OTP] SMS送信完了', [
            'phone' => $phone,
            'otp' => $otp,
            'result' => $smsSent,
        ]);

        // メールアドレスが指定されている場合はメールでも送信
        $emailSent = false;
        if ($email) {
            \Log::info('📧 [OTP] メール送信開始', [
                'email' => $email,
                'otp' => $otp,
            ]);
            $emailSent = $this->emailService->sendOtpEmail($email, $otp);
            \Log::info('📧 [OTP] メール送信完了', [
                'email' => $email,
                'otp' => $otp,
                'result' => $emailSent,
            ]);

            \Log::info('OTP送信完了', [
                'phone' => $phone,
                'email' => $email,
                'otp' => $otp,
                'sms_sent' => $smsSent,
                'email_sent' => $emailSent,
            ]);
        }

        // SMS または メールのどちらかが送信成功すればOK
        $success = $smsSent || $emailSent;

        return [
            'success' => $success,
            'sms_sent' => $smsSent,
            'email_sent' => $emailSent,
        ];
    }
    
    /**
     * OTPを検証
     *
     * @param string $phone
     * @param string $otp
     * @return bool
     */
    public function verifyOtp(string $phone, string $otp): bool
    {
        // 電話番号を正規化
        $normalizedPhone = PhoneHelper::normalize($phone);

        $verification = OtpVerification::where('phone', $normalizedPhone)
            ->where('otp_code', $otp)
            ->whereNull('verified_at')
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$verification) {
            // 試行回数を増やす
            OtpVerification::where('phone', $normalizedPhone)
                ->whereNull('verified_at')
                ->increment('attempts');

            return false;
        }

        // 検証成功
        $verification->update([
            'verified_at' => Carbon::now(),
        ]);

        return true;
    }
    
    /**
     * 再送信可能かチェック
     *
     * @param string $phone
     * @return bool
     */
    public function canResend(string $phone): bool
    {
        // 電話番号を正規化
        $normalizedPhone = PhoneHelper::normalize($phone);

        $lastOtp = OtpVerification::where('phone', $normalizedPhone)
            ->latest()
            ->first();

        if (!$lastOtp) {
            return true;
        }

        // 30秒以上経過していれば再送信可能
        return $lastOtp->created_at->diffInSeconds(Carbon::now()) >= 30;
    }
    
    /**
     * OTPコードを生成
     *
     * @return string
     */
    private function generateOtp(): string
    {
        // テスト用固定OTPを使用するかどうか
        if (config('services.sns.use_test_otp', false)) {
            return '123456';
        }

        // 本番用ランダムOTP生成（6桁の数字）
        return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }
}