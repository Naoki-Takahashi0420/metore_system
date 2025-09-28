<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;
use Carbon\Carbon;

class ReservationContextService
{
    /**
     * 予約コンテキストを暗号化してURLパラメータとして生成
     */
    public function encryptContext(array $context): string
    {
        // タイムスタンプを追加（有効期限チェック用）
        $context['created_at'] = Carbon::now()->timestamp;
        $context['expires_at'] = Carbon::now()->addHours(2)->timestamp; // 2時間有効

        return Crypt::encryptString(json_encode($context));
    }

    /**
     * URLパラメータから予約コンテキストを復号化
     */
    public function decryptContext(string $encryptedContext): ?array
    {
        try {
            $json = Crypt::decryptString($encryptedContext);
            $context = json_decode($json, true);

            if (!$context) {
                return null;
            }

            // 有効期限チェック
            if (isset($context['expires_at'])) {
                $expiresAt = Carbon::createFromTimestamp($context['expires_at']);
                if ($expiresAt->isPast()) {
                    \Log::warning('Reservation context expired', [
                        'expires_at' => $expiresAt->toISOString(),
                        'current_time' => Carbon::now()->toISOString()
                    ]);
                    return null;
                }
            }

            return $context;

        } catch (DecryptException $e) {
            \Log::warning('Failed to decrypt reservation context', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * カルテから予約用のコンテキストを生成
     */
    public function createMedicalRecordContext(int $customerId, int $storeId): string
    {
        $context = [
            'type' => 'medical_record',
            'customer_id' => $customerId,
            'store_id' => $storeId,
            'is_existing_customer' => true,
            'source' => 'medical_record'
        ];

        return $this->encryptContext($context);
    }

    /**
     * 新規予約用のコンテキストを生成
     */
    public function createNewReservationContext(?int $storeId = null): string
    {
        $context = [
            'type' => 'new_reservation',
            'is_existing_customer' => false,
            'source' => 'public'
        ];

        if ($storeId) {
            $context['store_id'] = $storeId;
        }

        return $this->encryptContext($context);
    }

    /**
     * コンテキストに新しい情報を追加
     */
    public function updateContext(array $currentContext, array $updates): string
    {
        $context = array_merge($currentContext, $updates);
        return $this->encryptContext($context);
    }

    /**
     * URLに追加するコンテキストパラメータを生成
     */
    public function getContextParameter(string $encryptedContext): string
    {
        return 'ctx=' . urlencode($encryptedContext);
    }

    /**
     * URLからコンテキストパラメータを取得
     */
    public function extractContextFromRequest(\Illuminate\Http\Request $request): ?array
    {
        $ctx = $request->get('ctx');
        if (!$ctx) {
            return null;
        }

        return $this->decryptContext($ctx);
    }

    /**
     * リダイレクト用のURLを生成（コンテキスト付き）
     */
    public function buildUrlWithContext(string $route, array $context, array $parameters = []): string
    {
        $encryptedContext = $this->encryptContext($context);
        $parameters['ctx'] = $encryptedContext;

        return route($route) . '?' . http_build_query($parameters);
    }
}