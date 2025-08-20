<?php

namespace App\Services;

use App\Models\OtpVerification;
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
        // 既存の未使用OTPを無効化
        OtpVerification::where('phone', $phone)
            ->whereNull('verified_at')
            ->delete();
        
        // 新しいOTPを生成
        $otp = $this->generateOtp();
        
        // データベースに保存
        OtpVerification::create([
            'phone' => $phone,
            'otp_code' => $otp,
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);
        
        // SMS送信
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
        $verification = OtpVerification::where('phone', $phone)
            ->where('otp_code', $otp)
            ->whereNull('verified_at')
            ->where('expires_at', '>', Carbon::now())
            ->first();
        
        if (!$verification) {
            // 試行回数を増やす
            OtpVerification::where('phone', $phone)
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
        $lastOtp = OtpVerification::where('phone', $phone)
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
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}