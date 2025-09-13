<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAccessToken;
use App\Services\LineTokenVerificationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LineLinkController extends Controller
{
    private LineTokenVerificationService $tokenVerificationService;

    public function __construct(LineTokenVerificationService $tokenVerificationService)
    {
        $this->tokenVerificationService = $tokenVerificationService;
    }

    /**
     * LINE連携処理
     */
    public function link(Request $request): JsonResponse
    {
        try {
            // レート制限チェック
            $key = 'line-link:' . $request->ip();
            if (RateLimiter::tooManyAttempts($key, 5)) {
                $seconds = RateLimiter::availableIn($key);
                return response()->json([
                    'success' => false,
                    'error' => 'Too many attempts',
                    'retry_after' => $seconds
                ], 429);
            }

            // リクエスト検証
            $validatedData = $request->validate([
                'id_token' => 'required|string',
                'customer_token' => 'required|string|size:32',
            ]);

            RateLimiter::hit($key);

            // CustomerAccessTokenを検索・検証（汎用トークンもサポート）
            $accessToken = CustomerAccessToken::where('token', $validatedData['customer_token'])
                ->whereIn('purpose', ['line_linking', 'line_linking_generic'])
                ->where('is_active', true)
                ->first();

            if (!$accessToken || !$accessToken->isValid()) {
                $this->logAuditEvent('line_link_attempt', null, 'failed', [
                    'error' => 'invalid_token',
                    'token' => $validatedData['customer_token'],
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Invalid or expired token'
                ], 400);
            }

            // 汎用トークンの場合は顧客情報を要求
            if ($accessToken->purpose === 'line_linking_generic') {
                return $this->handleGenericLinking($request, $accessToken, $validatedData);
            }

            // 通常のトークンの場合は顧客情報を取得
            $customer = $accessToken->customer;
            if (!$customer) {
                $this->logAuditEvent('line_link_attempt', null, 'failed', [
                    'error' => 'customer_not_found',
                    'token' => $validatedData['customer_token'],
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Customer not found'
                ], 400);
            }

            // IDトークンを検証
            try {
                $lineUserData = $this->tokenVerificationService->verifyIdToken($validatedData['id_token']);
            } catch (Exception $e) {
                // JWKs検証失敗時はAPI検証を試行
                try {
                    $lineUserData = $this->tokenVerificationService->verifyTokenWithAPI($validatedData['id_token']);
                } catch (Exception $apiError) {
                    $this->logAuditEvent('line_link_attempt', $customer->id, 'failed', [
                        'error' => 'token_verification_failed',
                        'message' => $apiError->getMessage(),
                        'ip' => $request->ip()
                    ]);

                    return response()->json([
                        'success' => false,
                        'error' => 'Invalid LINE token'
                    ], 400);
                }
            }

            // 既に別の顧客に連携されているかチェック
            $existingCustomer = Customer::findByLineUserId($lineUserData['user_id']);
            if ($existingCustomer && $existingCustomer->id !== $customer->id) {
                $this->logAuditEvent('line_link_attempt', $customer->id, 'failed', [
                    'error' => 'already_linked',
                    'line_user_id' => $lineUserData['user_id'],
                    'existing_customer_id' => $existingCustomer->id,
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'This LINE account is already linked to another customer'
                ], 409);
            }

            // LINE連携実行
            $customer->linkToLine($lineUserData['user_id'], [
                'name' => $lineUserData['name'],
                'picture' => $lineUserData['picture'],
                'email' => $lineUserData['email'],
            ]);

            // トークンを使用済みにする
            $accessToken->recordUsage();
            $accessToken->update(['is_active' => false]);

            // 成功ログ
            $this->logAuditEvent('line_link_success', $customer->id, 'success', [
                'line_user_id' => $lineUserData['user_id'],
                'line_name' => $lineUserData['name'],
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'LINE account linked successfully',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->full_name,
                    'line_linked' => true,
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('LINE link error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            $this->logAuditEvent('line_link_attempt', null, 'error', [
                'error' => 'system_error',
                'message' => $e->getMessage(),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred during linking'
            ], 500);
        }
    }

    /**
     * 連携状態確認
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'customer_token' => 'required|string|size:32',
            ]);

            $accessToken = CustomerAccessToken::where('token', $validatedData['customer_token'])
                ->where('purpose', 'line_linking')
                ->first();

            if (!$accessToken || !$accessToken->customer) {
                return response()->json([
                    'success' => false,
                    'error' => 'Token not found'
                ], 404);
            }

            $customer = $accessToken->customer;

            return response()->json([
                'success' => true,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->full_name,
                    'line_linked' => $customer->isLinkedToLine(),
                    'line_notifications_enabled' => $customer->line_notifications_enabled,
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('LINE status check error', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred'
            ], 500);
        }
    }

    /**
     * 汎用トークンでの連携処理（顧客情報を特定する必要がある）
     */
    private function handleGenericLinking(Request $request, CustomerAccessToken $accessToken, array $validatedData): JsonResponse
    {
        try {
            // 追加の顧客特定情報を要求
            $customerData = $request->validate([
                'customer_phone' => 'required|string|max:20',
                'customer_name' => 'sometimes|string|max:100',
            ]);

            // IDトークンを検証してLINE User IDを取得
            $lineUserData = $this->tokenVerificationService->verifyIdToken($validatedData['id_token']);

            // 電話番号で顧客を検索
            $customer = Customer::where('phone', $customerData['customer_phone'])->first();

            if (!$customer) {
                $this->logAuditEvent('line_link_generic_attempt', null, 'failed', [
                    'error' => 'customer_not_found',
                    'phone' => $customerData['customer_phone'],
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Customer not found with provided phone number'
                ], 404);
            }

            // 既に別の顧客に連携されているかチェック
            $existingCustomer = Customer::findByLineUserId($lineUserData['user_id']);
            if ($existingCustomer && $existingCustomer->id !== $customer->id) {
                $this->logAuditEvent('line_link_generic_attempt', $customer->id, 'failed', [
                    'error' => 'already_linked',
                    'line_user_id' => $lineUserData['user_id'],
                    'existing_customer_id' => $existingCustomer->id,
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'This LINE account is already linked to another customer'
                ], 409);
            }

            // LINE連携実行
            $customer->linkToLine($lineUserData['user_id'], [
                'name' => $lineUserData['name'],
                'picture' => $lineUserData['picture'],
                'email' => $lineUserData['email'],
            ]);

            // トークンを使用済みにする
            $accessToken->recordUsage();
            $accessToken->update(['is_active' => false]);

            // 成功ログ
            $this->logAuditEvent('line_link_generic_success', $customer->id, 'success', [
                'line_user_id' => $lineUserData['user_id'],
                'line_name' => $lineUserData['name'],
                'phone' => $customer->phone,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'LINE account linked successfully',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->full_name,
                    'line_linked' => true,
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('LINE generic link error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred during linking'
            ], 500);
        }
    }

    /**
     * 監査ログ記録
     */
    private function logAuditEvent(string $event, ?int $customerId, string $status, array $details = []): void
    {
        Log::info('LINE linking audit log', [
            'event' => $event,
            'customer_id' => $customerId,
            'status' => $status,
            'details' => $details,
            'timestamp' => now()->toISOString(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}