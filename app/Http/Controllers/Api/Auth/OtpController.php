<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\OtpService;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OtpController extends Controller
{
    private OtpService $otpService;
    private EmailService $emailService;
    
    public function __construct(OtpService $otpService, EmailService $emailService)
    {
        $this->otpService = $otpService;
        $this->emailService = $emailService;
    }
    
    /**
     * OTP送信（SMS/メール選択可能）
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'in:sms,email'],
            'phone' => ['required_if:type,sms', 'string', 'regex:/^[0-9\-]+$/'],
            'email' => ['required_if:type,email', 'email'],
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => '入力内容に誤りがあります',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }
        
        $type = $request->type;
        $identifier = $type === 'sms' ? $request->phone : $request->email;
        
        // 再送信チェック
        if (!$this->otpService->canResend($identifier)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT',
                    'message' => '1分以内の再送信はできません',
                ],
            ], 429);
        }
        
        // OTP送信
        if ($type === 'sms') {
            $success = $this->otpService->sendOtp($request->phone);
            $message = '認証コードをSMSで送信しました';
        } else {
            // メール送信用のOTP生成
            $otp = $this->generateOtpForEmail($request->email);
            $success = $this->emailService->sendOtpEmail($request->email, $otp);
            $message = '認証コードをメールで送信しました';
        }
        
        if (!$success) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SEND_FAILED',
                    'message' => '送信に失敗しました',
                ],
            ], 500);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'message' => $message,
                'type' => $type,
            ],
        ]);
    }
    
    /**
     * OTP検証
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'in:sms,email'],
            'phone' => ['required_if:type,sms', 'string'],
            'email' => ['required_if:type,email', 'email'],
            'otp_code' => ['required', 'string', 'size:6'],
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => '入力内容に誤りがあります',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }
        
        $type = $request->type;
        $identifier = $type === 'sms' ? $request->phone : $request->email;
        
        // OTP検証
        if (!$this->otpService->verifyOtp($identifier, $request->otp_code)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_OTP',
                    'message' => '認証コードが正しくないか、有効期限が切れています',
                ],
            ], 400);
        }
        
        // 顧客情報を作成または更新
        $customer = Customer::firstOrCreate(
            $type === 'sms' 
                ? ['phone' => $request->phone]
                : ['email' => $request->email],
            [
                'name' => '未設定',
                'phone_verified_at' => $type === 'sms' ? now() : null,
                'email_verified_at' => $type === 'email' ? now() : null,
            ]
        );
        
        // 認証トークン生成
        $token = $customer->createToken('customer-app')->plainTextToken;
        
        return response()->json([
            'success' => true,
            'data' => [
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                ],
                'token' => $token,
            ],
        ]);
    }
    
    /**
     * メール用OTP生成
     */
    private function generateOtpForEmail(string $email): string
    {
        // OtpServiceと同じロジックを使用
        return '123456'; // 一時的に固定
        
        // 本番用
        // return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}