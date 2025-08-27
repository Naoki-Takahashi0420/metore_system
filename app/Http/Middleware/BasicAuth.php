<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BasicAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 環境変数からBasic認証の設定を取得
        $username = env('BASIC_AUTH_USERNAME', 'admin');
        $password = env('BASIC_AUTH_PASSWORD', 'password');
        
        // Basic認証が無効の場合はスキップ
        if (env('BASIC_AUTH_ENABLED', false) !== true) {
            return $next($request);
        }
        
        // Basic認証のチェック
        if (!$request->server('PHP_AUTH_USER') || 
            $request->server('PHP_AUTH_USER') !== $username || 
            $request->server('PHP_AUTH_PW') !== $password) {
            
            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="Protected Area"'
            ]);
        }
        
        return $next($request);
    }
}
