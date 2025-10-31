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

            // IDトークンを検証（.envのChannel IDを使用）
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

    /**
     * 予約番号ベースのLINE連携処理
     */
    public function linkByReservation(Request $request): JsonResponse
    {
        try {
            // レート制限チェック
            $key = 'line-link-reservation:' . $request->ip();
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
                'reservation_number' => 'required|string',
            ]);

            RateLimiter::hit($key);

            // 予約情報を取得
            $reservation = \App\Models\Reservation::where('reservation_number', $validatedData['reservation_number'])
                ->with(['customer', 'store'])
                ->first();

            if (!$reservation) {
                $this->logAuditEvent('line_link_reservation_attempt', null, 'failed', [
                    'error' => 'reservation_not_found',
                    'reservation_number' => $validatedData['reservation_number'],
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Reservation not found'
                ], 404);
            }

            $customer = $reservation->customer;
            $store = $reservation->store;

            // 店舗のLINE連携が有効かチェック
            if (!$store->line_enabled || !$store->line_liff_id) {
                $this->logAuditEvent('line_link_reservation_attempt', $customer->id, 'failed', [
                    'error' => 'line_not_enabled',
                    'store_id' => $store->id,
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'LINE linking is not enabled for this store'
                ], 400);
            }

            // IDトークンを検証（店舗のChannel IDを使用）
            try {
                $lineUserData = $this->tokenVerificationService->verifyIdToken(
                    $validatedData['id_token'],
                    $store->line_channel_id
                );
            } catch (Exception $e) {
                // JWKs検証失敗時はAPI検証を試行（店舗のChannel IDを使用）
                try {
                    $lineUserData = $this->tokenVerificationService->verifyTokenWithAPI(
                        $validatedData['id_token'],
                        $store->line_channel_id
                    );
                } catch (Exception $apiError) {
                    $this->logAuditEvent('line_link_reservation_attempt', $customer->id, 'failed', [
                        'error' => 'token_verification_failed',
                        'message' => $apiError->getMessage(),
                        'store_id' => $store->id,
                        'has_channel_id' => !empty($store->line_channel_id),
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
                $this->logAuditEvent('line_link_reservation_attempt', $customer->id, 'failed', [
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

            // 成功ログ
            $this->logAuditEvent('line_link_reservation_success', $customer->id, 'success', [
                'line_user_id' => $lineUserData['user_id'],
                'line_name' => $lineUserData['name'],
                'reservation_number' => $reservation->reservation_number,
                'store_id' => $store->id,
                'ip' => $request->ip()
            ]);

            // 予約詳細をLINEに送信
            $lineMessageSent = $this->sendReservationDetailsToLine($customer, $reservation, $store);
            
            // LINE送信成功時は確認通知送信済みフラグを設定（統一的な管理）
            if ($lineMessageSent) {
                $reservation->update([
                    'confirmation_sent' => true,
                    'confirmation_sent_at' => now(),
                    'confirmation_method' => 'line'
                ]);
                \Log::info('LINE連携時の予約詳細送信成功、確認通知フラグ設定', [
                    'reservation_id' => $reservation->id,
                    'customer_id' => $customer->id,
                    'sent_at' => now()->toISOString()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'LINE account linked successfully',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->full_name,
                    'line_linked' => true,
                ],
                'reservation' => [
                    'number' => $reservation->reservation_number,
                    'store_name' => $store->name
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('LINE reservation link error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            $this->logAuditEvent('line_link_reservation_attempt', null, 'error', [
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
     * 予約詳細をLINEに送信
     */
    private function sendReservationDetailsToLine(Customer $customer, \App\Models\Reservation $reservation, \App\Models\Store $store): bool
    {
        try {
            if (!$customer->line_user_id) {
                return false;
            }

            $lineMessageService = app(\App\Services\LineMessageService::class);
            
            // 店舗のLINEチャネルトークンを設定
            if ($store->line_channel_access_token) {
                $lineMessageService->setChannelToken($store->line_channel_access_token);
            }
            
            // 予約詳細メッセージを構築
            $reservationDate = \Carbon\Carbon::parse($reservation->reservation_date);
            $startTime = \Carbon\Carbon::parse($reservation->start_time);
            $endTime = \Carbon\Carbon::parse($reservation->end_time);
            
            $message = [
                'type' => 'flex',
                'altText' => '予約詳細',
                'contents' => [
                    'type' => 'bubble',
                    'header' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '🎉 LINE連携完了！',
                                'weight' => 'bold',
                                'size' => 'lg',
                                'color' => '#ffffff'
                            ],
                            [
                                'type' => 'text',
                                'text' => 'ご予約詳細',
                                'size' => 'sm',
                                'color' => '#ffffff'
                            ]
                        ],
                        'backgroundColor' => '#059669',
                        'paddingAll' => 'lg'
                    ],
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'contents' => [
                                    [
                                        'type' => 'text',
                                        'text' => '予約番号',
                                        'size' => 'sm',
                                        'color' => '#888888'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $reservation->reservation_number,
                                        'weight' => 'bold',
                                        'size' => 'lg'
                                    ]
                                ],
                                'margin' => 'md'
                            ],
                            [
                                'type' => 'separator',
                                'margin' => 'lg'
                            ],
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'contents' => [
                                    [
                                        'type' => 'text',
                                        'text' => '日時',
                                        'size' => 'sm',
                                        'color' => '#888888'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $reservationDate->locale('ja')->isoFormat('YYYY年M月D日(ddd)'),
                                        'weight' => 'bold'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $startTime->format('H:i') . ' - ' . $endTime->format('H:i'),
                                        'weight' => 'bold'
                                    ]
                                ],
                                'margin' => 'lg'
                            ],
                            [
                                'type' => 'separator',
                                'margin' => 'lg'
                            ],
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'contents' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'メニュー',
                                        'size' => 'sm',
                                        'color' => '#888888'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $reservation->menu->name ?? '-',
                                        'weight' => 'bold'
                                    ]
                                ],
                                'margin' => 'lg'
                            ],
                            [
                                'type' => 'separator',
                                'margin' => 'lg'
                            ],
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'contents' => [
                                    [
                                        'type' => 'text',
                                        'text' => '店舗',
                                        'size' => 'sm',
                                        'color' => '#888888'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $store->name,
                                        'weight' => 'bold'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $store->phone,
                                        'size' => 'sm',
                                        'color' => '#888888'
                                    ]
                                ],
                                'margin' => 'lg'
                            ]
                        ]
                    ],
                    'footer' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => 'LINEでのご予約管理・リマインダー通知をお楽しみください！',
                                'size' => 'sm',
                                'color' => '#888888',
                                'wrap' => true
                            ]
                        ],
                        'margin' => 'lg'
                    ]
                ]
            ];

            $result = $lineMessageService->sendMessage($customer->line_user_id, $message);

            if ($result) {
                Log::info('Reservation details sent to LINE', [
                    'customer_id' => $customer->id,
                    'reservation_number' => $reservation->reservation_number,
                    'line_user_id' => $customer->line_user_id
                ]);
                return true;
            } else {
                Log::warning('Failed to send reservation details to LINE', [
                    'customer_id' => $customer->id,
                    'reservation_number' => $reservation->reservation_number
                ]);
                return false;
            }

        } catch (Exception $e) {
            Log::error('Failed to send reservation details to LINE', [
                'error' => $e->getMessage(),
                'customer_id' => $customer->id,
                'reservation_number' => $reservation->reservation_number ?? null
            ]);
            return false;
        }
    }
}