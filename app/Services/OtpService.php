<?php

namespace App\Services;

use App\Models\OtpVerification;
use App\Helpers\PhoneHelper;
use Carbon\Carbon;
use Illuminate\Support\Str;

class OtpService
{
    private SmsService $smsService;
    
    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }
    
    /**
     * OTPを生成して送信
     *
     * @param string $phone
     * @return bool
     */
    public function sendOtp(string $phone): bool
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

        // データベースに保存（正規化した電話番号で保存）
        OtpVerification::create([
            'phone' => $normalizedPhone,
            'otp_code' => $otp,
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        // SMS送信（元の電話番号形式で送信）
        return $this->smsService->sendOtp($phone, $otp);
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

        // 1分以上経過していれば再送信可能
        return $lastOtp->created_at->diffInMinutes(Carbon::now()) >= 1;
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