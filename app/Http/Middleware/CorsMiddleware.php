<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // 許可するオリジン（LIFF用）
        $allowedOrigins = [
            'https://liff.line.me',
            'https://liff-web.line.me',
            'https://reservation.meno-training.com',
            'http://localhost:8000',
            'http://127.0.0.1:8000',
        ];

        $origin = $request->headers->get('Origin');
        
        // オリジンが許可リストにある場合のみ許可
        if (in_array($origin, $allowedOrigins)) {
            $headers = [
                'Access-Control-Allow-Origin' => $origin,
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, Accept, Origin',
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Max-Age' => '86400',
            ];
        } else {
            $headers = [];
        }

        // プリフライトリクエストの場合
        if ($request->isMethod('OPTIONS')) {
            return response()->json(['status' => 'OK'], 200, $headers);
        }

        $response = $next($request);

        // レスポンスにCORSヘッダーを追加
        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}