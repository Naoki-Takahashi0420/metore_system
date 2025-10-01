<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\JWK;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LineTokenVerificationService
{
    private Client $httpClient;

    public function __construct()
    {
        $this->httpClient = new Client(['timeout' => 10]);
    }

    /**
     * LINEのIDトークンを検証
     */
    public function verifyIdToken(string $idToken): array
    {
        try {
            // JWTヘッダーをデコード
            $header = $this->getTokenHeader($idToken);
            
            // キーIDを取得
            $keyId = $header['kid'] ?? null;
            if (!$keyId) {
                throw new Exception('Key ID not found in token header');
            }

            // LINE JWKsを取得してキーを見つける
            $jwks = $this->getLineJWKs();
            $publicKey = $this->findPublicKey($jwks, $keyId);

            // トークンを検証してペイロードを取得
            // findPublicKey now returns a Key object directly
            $payload = JWT::decode($idToken, $publicKey);
            
            // ペイロードを検証
            $this->validateTokenPayload($payload);

            return [
                'user_id' => $payload->sub,
                'name' => $payload->name ?? null,
                'picture' => $payload->picture ?? null,
                'email' => $payload->email ?? null,
            ];

        } catch (Exception $e) {
            Log::error('LINE ID token verification failed', [
                'error' => $e->getMessage(),
                'token' => substr($idToken, 0, 50) . '...'
            ]);
            throw new Exception('Invalid ID token: ' . $e->getMessage());
        }
    }

    /**
     * LINE Verify APIを使用してトークンを検証
     */
    public function verifyTokenWithAPI(string $idToken, ?string $channelId = null): array
    {
        try {
            // Channel IDは引数で渡されるか、環境変数から取得
            $clientId = $channelId ?? config('services.line.channel_id');

            if (!$clientId) {
                throw new Exception('LINE Channel ID is not configured');
            }

            $response = $this->httpClient->post('https://api.line.me/oauth2/v2.1/verify', [
                'form_params' => [
                    'id_token' => $idToken,
                    'client_id' => $clientId,
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['sub'])) {
                throw new Exception('Invalid token response');
            }

            return [
                'user_id' => $data['sub'],
                'name' => $data['name'] ?? null,
                'picture' => $data['picture'] ?? null,
                'email' => $data['email'] ?? null,
            ];

        } catch (Exception $e) {
            Log::error('LINE token verification API failed', [
                'error' => $e->getMessage(),
                'token' => substr($idToken, 0, 50) . '...',
                'channel_id' => $channelId ?? 'not provided'
            ]);
            throw new Exception('Token verification failed: ' . $e->getMessage());
        }
    }

    /**
     * JWTヘッダーを取得
     */
    private function getTokenHeader(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Invalid JWT format');
        }

        $header = json_decode(base64_decode($parts[0]), true);
        if (!$header) {
            throw new Exception('Invalid JWT header');
        }

        return $header;
    }

    /**
     * LINE JWKsを取得（キャッシュ付き）
     */
    private function getLineJWKs(): array
    {
        return Cache::remember('line_jwks', 3600, function () {
            try {
                $response = $this->httpClient->get('https://api.line.me/oauth2/v2.1/certs');
                return json_decode($response->getBody()->getContents(), true);
            } catch (Exception $e) {
                Log::error('Failed to fetch LINE JWKs', ['error' => $e->getMessage()]);
                throw new Exception('Failed to fetch LINE public keys');
            }
        });
    }

    /**
     * 公開鍵を検索
     */
    private function findPublicKey(array $jwks, string $keyId): Key
    {
        foreach ($jwks['keys'] as $key) {
            if ($key['kid'] === $keyId) {
                // JWK::parseKey returns a Key object that can be used directly with JWT::decode
                return JWK::parseKey($key);
            }
        }

        throw new Exception('Public key not found for key ID: ' . $keyId);
    }

    /**
     * トークンペイロードを検証
     */
    private function validateTokenPayload(object $payload): void
    {
        $now = time();
        
        // 有効期限チェック
        if (!isset($payload->exp) || $payload->exp < $now) {
            throw new Exception('Token has expired');
        }

        // 発行時刻チェック
        if (!isset($payload->iat) || $payload->iat > $now + 300) { // 5分のクロックスキューを許可
            throw new Exception('Token issued in the future');
        }

        // 発行者チェック
        if (!isset($payload->iss) || $payload->iss !== 'https://access.line.me') {
            throw new Exception('Invalid issuer');
        }

        // オーディエンスチェック
        $expectedChannelId = config('services.line.channel_id');
        if (!isset($payload->aud)) {
            throw new Exception('Audience not found in token');
        }
        if ($payload->aud !== $expectedChannelId) {
            Log::error('Audience mismatch', [
                'expected' => $expectedChannelId,
                'actual' => $payload->aud,
                'payload' => json_encode($payload)
            ]);
            throw new Exception('Invalid audience - expected: ' . $expectedChannelId . ', got: ' . $payload->aud);
        }

        // ユーザーIDチェック
        if (!isset($payload->sub) || empty($payload->sub)) {
            throw new Exception('User ID not found');
        }
    }
}