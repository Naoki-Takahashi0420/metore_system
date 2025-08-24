<?php

use App\Http\Controllers\Api\Auth\CustomerAuthController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\CustomerCheckController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// 顧客認証
Route::prefix('auth')->group(function () {
    Route::prefix('customer')->group(function () {
        Route::post('send-otp', [CustomerAuthController::class, 'sendOtp']);
        Route::post('verify-otp', [CustomerAuthController::class, 'verifyOtp']);
        Route::post('register', [CustomerAuthController::class, 'register']);
        
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [CustomerAuthController::class, 'logout']);
        });
    });
});

// 公開API（店舗・メニュー情報）
Route::apiResource('stores', StoreController::class)->only(['index', 'show']);
Route::get('menus/upsell', [MenuController::class, 'upsell']);
Route::apiResource('menus', MenuController::class)->only(['index', 'show']);

// 予約可能時間の取得（認証不要）
Route::get('availability/slots', [AvailabilityController::class, 'getAvailableSlots']);
Route::get('availability/days', [AvailabilityController::class, 'getAvailableDays']);

// 顧客チェック（認証不要）
Route::post('customer/check-phone', [CustomerCheckController::class, 'checkPhone']);

// 予約作成（認証不要）
// Public reservation functionality moved to web routes

// 顧客向けAPI（認証必須）
Route::middleware('auth:sanctum')->prefix('customer')->group(function () {
    // 予約管理
    Route::get('reservations', [ReservationController::class, 'customerReservations']);
    Route::get('reservations/{id}', [ReservationController::class, 'customerReservationDetail']);
    Route::post('reservations/{id}/cancel', [ReservationController::class, 'cancelReservation']);
    // プロフィール管理
    Route::get('profile', [CustomerController::class, 'show']);
    Route::put('profile', [CustomerController::class, 'update']);
    // カルテ管理
    Route::get('medical-records', [CustomerController::class, 'getMedicalRecords']);
    // 通知設定
    Route::get('notification-preferences', [NotificationPreferenceController::class, 'show']);
    Route::put('notification-preferences', [NotificationPreferenceController::class, 'update']);
});

// SMS配信停止（認証不要・トークンベース）
Route::post('unsubscribe-sms', [NotificationPreferenceController::class, 'unsubscribe']);

// 勤怠管理API（認証必須）
Route::middleware('auth:sanctum')->prefix('time-tracking')->group(function () {
    Route::post('clock-in/{shift}', [\App\Http\Controllers\Api\TimeTrackingController::class, 'clockIn']);
    Route::post('start-break/{shift}', [\App\Http\Controllers\Api\TimeTrackingController::class, 'startBreak']);
    Route::post('end-break/{shift}', [\App\Http\Controllers\Api\TimeTrackingController::class, 'endBreak']);
    Route::post('clock-out/{shift}', [\App\Http\Controllers\Api\TimeTrackingController::class, 'clockOut']);
});

// 管理者向けAPI（認証必須）
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    // 全リソース管理
    Route::apiResource('stores', StoreController::class)->except(['index', 'show']);
    Route::apiResource('menus', MenuController::class)->except(['index', 'show']);
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('reservations', ReservationController::class);
});