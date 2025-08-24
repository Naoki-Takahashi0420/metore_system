<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CustomerAuthController extends Controller
{
    private OtpService $otpService;
    
    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }
    
    /**
     * OTP送信
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^[0-9\-]+$/'],
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
        
        // 再送信チェック
        if (!$this->otpService->canResend($request->phone)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT',
                    'message' => '1分以内の再送信はできません',
                ],
            ], 429);
        }
        
        // OTP送信
        if (!$this->otpService->sendOtp($request->phone)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SMS_SEND_FAILED',
                    'message' => 'SMS送信に失敗しました',
                ],
            ], 500);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'message' => '認証コードを送信しました',
            ],
        ]);
    }
    
    /**
     * OTP検証とログイン/登録
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^[0-9\-]+$/'],
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
        
        // OTP検証
        if (!$this->otpService->verifyOtp($request->phone, $request->otp_code)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_OTP',
                    'message' => '認証コードが正しくないか、有効期限が切れています',
                ],
            ], 401);
        }
        
        // 顧客を取得または仮作成
        $customer = Customer::where('phone', $request->phone)->first();
        
        if (!$customer) {
            // 新規顧客の場合は仮トークンを返す
            $tempToken = Str::random(60);
            
            // セッションに一時保存
            session([
                'temp_customer_' . $tempToken => [
                    'phone' => $request->phone,
                    'expires_at' => now()->addMinutes(30),
                ],
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'is_new_customer' => true,
                    'temp_token' => $tempToken,
                ],
            ]);
        }
        
        // 既存顧客の場合
        $customer->update([
            'phone_verified_at' => now(),
            'last_visit_at' => now(),
        ]);
        
        // トークン生成
        $token = $customer->createToken('customer-auth')->plainTextToken;
        
        return response()->json([
            'success' => true,
            'data' => [
                'is_new_customer' => false,
                'token' => $token,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->full_name,
                    'last_name' => $customer->last_name,
                    'first_name' => $customer->first_name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                ],
            ],
        ]);
    }
    
    /**
     * 顧客登録
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'temp_token' => ['required', 'string'],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name_kana' => ['nullable', 'string', 'max:100', 'regex:/^[ァ-ヶー]+$/u'],
            'first_name_kana' => ['nullable', 'string', 'max:100', 'regex:/^[ァ-ヶー]+$/u'],
            'email' => ['nullable', 'email', 'unique:customers,email'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{3}-?\d{4}$/'],
            'address' => ['nullable', 'string'],
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
        
        // 仮トークン検証
        $tempData = session('temp_customer_' . $request->temp_token);
        
        if (!$tempData || now()->isAfter($tempData['expires_at'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_TOKEN',
                    'message' => 'トークンが無効です',
                ],
            ], 401);
        }
        
        // 顧客作成
        $customer = Customer::create([
            'phone' => $tempData['phone'],
            'phone_verified_at' => now(),
            'last_name' => $request->last_name,
            'first_name' => $request->first_name,
            'last_name_kana' => $request->last_name_kana,
            'first_name_kana' => $request->first_name_kana,
            'email' => $request->email,
            'birth_date' => $request->birth_date,
            'gender' => $request->gender,
            'postal_code' => $request->postal_code,
            'address' => $request->address,
        ]);
        
        // セッションクリア
        session()->forget('temp_customer_' . $request->temp_token);
        
        // トークン生成
        $token = $customer->createToken('customer-auth')->plainTextToken;
        
        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->full_name,
                    'last_name' => $customer->last_name,
                    'first_name' => $customer->first_name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                ],
            ],
        ]);
    }
    
    /**
     * ログアウト
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'ログアウトしました',
            ],
        ]);
    }
}