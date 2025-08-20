<?php

use App\Http\Controllers\Api\Auth\CustomerAuthController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\CustomerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// 顧客認証
Route::prefix('auth')->group(function () {
    Route::post('send-otp', [CustomerAuthController::class, 'sendOtp']);
    Route::post('verify-otp', [CustomerAuthController::class, 'verifyOtp']);
    Route::post('register', [CustomerAuthController::class, 'register']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [CustomerAuthController::class, 'logout']);
    });
});

// 公開API（店舗・メニュー情報）
Route::apiResource('stores', StoreController::class)->only(['index', 'show']);
Route::apiResource('menus', MenuController::class)->only(['index', 'show']);

// 顧客向けAPI（認証必須）
Route::middleware('auth:sanctum')->prefix('customer')->group(function () {
    // 予約管理
    Route::apiResource('reservations', ReservationController::class);
    Route::get('profile', [CustomerController::class, 'show']);
    Route::put('profile', [CustomerController::class, 'update']);
});

// 管理者向けAPI（認証必須）
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    // 全リソース管理
    Route::apiResource('stores', StoreController::class)->except(['index', 'show']);
    Route::apiResource('menus', MenuController::class)->except(['index', 'show']);
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('reservations', ReservationController::class);
});