<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.basic' => \App\Http\Middleware\BasicAuth::class,
            'cors' => \App\Http\Middleware\CorsMiddleware::class,
        ]);

        // LINE連携API用のCORS設定
        $middleware->group('api', [
            \App\Http\Middleware\CorsMiddleware::class,
        ]);

        // Livewireファイルアップロード用のCSRF除外
        $middleware->validateCsrfTokens(except: [
            'livewire/upload-file',
            'livewire/preview-file/*',
        ]);

        // API認証失敗時にJSONレスポンスを返す（リダイレクトしない）
        $middleware->redirectGuestsTo(fn ($request) =>
            $request->expectsJson()
                ? response()->json(['error' => 'Unauthenticated'], 401)
                : route('filament.admin.auth.login')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
