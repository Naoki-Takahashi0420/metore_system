<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\OtpService;
use App\Helpers\PhoneHelper;
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
        
        // 再送信チェック（30秒以内の再送信を制限）
        if (!$this->otpService->canResend($request->phone)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT',
                    'message' => '30秒以内の再送信はできません。しばらくお待ちください。',
                ],
            ], 429);
        }

        // 既存顧客のメールアドレスを取得（再送信時のメール送信用）
        $normalizedPhone = PhoneHelper::normalize($request->phone);
        $customer = Customer::where('phone', $normalizedPhone)
            ->orWhere('phone', $request->phone)
            ->whereNotNull('email')
            ->first();

        $email = $customer && $customer->email ? $customer->email : null;

        // OTP送信（メールアドレスがあればメールでも送信）
        if (!$this->otpService->sendOtp($request->phone, $email)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SMS_SEND_FAILED',
                    'message' => 'SMS送信に失敗しました',
                ],
            ], 500);
        }
        
        // 送信先に応じてメッセージを変更
        $message = '認証コードを送信しました';
        if ($email) {
            $message = '認証コードをSMSとメールに送信しました';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => $message,
                'email_sent' => $email !== null, // メール送信したかどうかをフロントに伝える
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
            'remember_me' => ['boolean'],
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

        // 電話番号を正規化して顧客を検索（複数店舗対応）
        $normalizedPhone = PhoneHelper::normalize($request->phone);
        $customers = Customer::where('phone', $normalizedPhone)
            ->orWhere('phone', $request->phone)
            ->with('store')
            ->get();

        if ($customers->isEmpty()) {
            // 電話番号がDBに存在しない場合はアクセス拒否
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_RESERVATION_HISTORY',
                    'message' => '予約履歴が見つかりません。初回のお客様は新規予約からお申し込みください。',
                    'redirect_to_booking' => true,
                ],
            ], 404);
        }

        // 予約履歴があるかチェック（いずれかの店舗で予約があればOK）
        $customersWithReservations = $customers->filter(function ($customer) {
            return $customer->reservations()
                ->whereIn('status', ['confirmed', 'completed', 'booked'])
                ->exists();
        });

        // 予約履歴がある顧客を優先、なければインポートされた顧客でもログイン可能
        if ($customersWithReservations->isNotEmpty()) {
            // 予約履歴がある場合：最新の予約がある店舗を優先
            $customer = $customersWithReservations->sortByDesc(function ($c) {
                $latestReservation = $c->reservations()
                    ->whereIn('status', ['confirmed', 'completed', 'booked'])
                    ->latest('reservation_date')
                    ->latest('start_time')
                    ->first();
                return $latestReservation ? $latestReservation->reservation_date . ' ' . $latestReservation->start_time : '';
            })->first();
        } else {
            // 予約履歴がない場合：インポートされた顧客でもログイン可能
            // 最初の顧客レコードを使用
            $customer = $customers->first();
        }
        $customer->update([
            'phone_verified_at' => now(),
            'last_visit_at' => now(),
        ]);

        // トークン生成（Remember Meオプションに応じて有効期限を設定）
        $rememberMe = $request->boolean('remember_me', false);

        // Remember Me設定に応じてトークンの有効期限を設定
        $tokenName = $rememberMe ? 'customer-auth-remember' : 'customer-auth';
        $expiresAt = $rememberMe ? now()->addDays(30) : now()->addHours(2);

        $token = $customer->createToken($tokenName, ['*'], $expiresAt)->plainTextToken;

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
     * 店舗選択（複数店舗に登録がある場合）
     */
    public function selectStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'temp_token' => ['required', 'string'],
            'customer_id' => ['required', 'integer'],
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
        $tempData = session('temp_customer_multistore_' . $request->temp_token);

        if (!$tempData || now()->isAfter($tempData['expires_at'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_TOKEN',
                    'message' => 'トークンが無効です',
                ],
            ], 401);
        }

        // 指定されたcustomer_idが正規化された電話番号と一致するか確認
        $customer = Customer::where('id', $request->customer_id)
            ->where('phone', $tempData['phone'])
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_CUSTOMER',
                    'message' => '不正な顧客IDです',
                ],
            ], 400);
        }

        // 予約履歴があるか再確認
        $hasReservations = $customer->reservations()
            ->whereIn('status', ['confirmed', 'completed', 'booked'])
            ->exists();

        if (!$hasReservations) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_RESERVATION_HISTORY',
                    'message' => 'この店舗での予約履歴が見つかりません。',
                    'redirect_to_booking' => true,
                ],
            ], 403);
        }

        // セッションクリア
        session()->forget('temp_customer_multistore_' . $request->temp_token);

        // 最終訪問日時を更新
        $customer->update([
            'phone_verified_at' => now(),
            'last_visit_at' => now(),
        ]);

        // トークン生成
        $rememberMe = $tempData['remember_me'] ?? false;
        $tokenName = $rememberMe ? 'customer-auth-remember' : 'customer-auth';
        $expiresAt = $rememberMe ? now()->addDays(30) : now()->addHours(2);

        $token = $customer->createToken($tokenName, ['*'], $expiresAt)->plainTextToken;

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

        // トークン生成（Remember Meオプションに応じて有効期限を設定）
        $rememberMe = $request->boolean('remember_me', false);

        // Remember Me設定に応じてトークンの有効期限を設定
        $tokenName = $rememberMe ? 'customer-auth-remember' : 'customer-auth';
        $expiresAt = $rememberMe ? now()->addDays(30) : now()->addHours(2);

        $token = $customer->createToken($tokenName, ['*'], $expiresAt)->plainTextToken;
        
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
     * 店舗切り替え（マイページ内）
     * 既に認証済みなので、OTP不要で店舗切り替え可能
     */
    public function switchStore(Request $request)
    {
        // 認証済みの顧客を取得
        $currentCustomer = $request->user();

        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^[0-9\-]+$/'],
            'customer_id' => ['required', 'integer'],
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

        // 現在のユーザーの電話番号と一致するか確認
        $normalizedPhone = PhoneHelper::normalize($request->phone);

        if ($currentCustomer->phone !== $normalizedPhone && $currentCustomer->phone !== $request->phone) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PHONE_MISMATCH',
                    'message' => '電話番号が一致しません',
                ],
            ], 403);
        }

        // 切り替え先の顧客データを取得
        $targetCustomer = Customer::where('id', $request->customer_id)
            ->where(function ($query) use ($normalizedPhone, $request) {
                $query->where('phone', $normalizedPhone)
                    ->orWhere('phone', $request->phone);
            })
            ->first();

        if (!$targetCustomer) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_CUSTOMER',
                    'message' => '不正な顧客IDです',
                ],
            ], 400);
        }

        // 予約履歴チェックは不要（インポートされた顧客でも切り替え可能）

        // 現在のトークンを削除
        $currentCustomer->currentAccessToken()->delete();

        // 最終訪問日時を更新
        $targetCustomer->update([
            'phone_verified_at' => now(),
            'last_visit_at' => now(),
        ]);

        // 新しいトークンを発行
        $tokenName = 'customer-auth';
        $expiresAt = now()->addHours(2);

        $token = $targetCustomer->createToken($tokenName, ['*'], $expiresAt)->plainTextToken;

        // 切り替え先の店舗IDを取得（予約履歴から推測）
        $targetStoreId = $request->input('store_id');
        if (!$targetStoreId) {
            // store_idが指定されていない場合は、最新の予約から取得
            $latestReservation = $targetCustomer->reservations()
                ->whereIn('status', ['confirmed', 'completed', 'booked'])
                ->latest('reservation_date')
                ->first();
            $targetStoreId = $latestReservation ? $latestReservation->store_id : null;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'customer' => [
                    'id' => $targetCustomer->id,
                    'name' => $targetCustomer->full_name,
                    'last_name' => $targetCustomer->last_name,
                    'first_name' => $targetCustomer->first_name,
                    'phone' => $targetCustomer->phone,
                    'email' => $targetCustomer->email,
                    'store_id' => $targetStoreId, // 切り替え先の店舗ID
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