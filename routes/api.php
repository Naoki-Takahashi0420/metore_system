<?php

use App\Http\Controllers\Api\Auth\CustomerAuthController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\CustomerCheckController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\LineLinkController;
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
Route::post('check-availability', [\App\Http\Controllers\PublicReservationController::class, 'checkAvailability']);

// 顧客チェック（認証不要）
Route::post('customer/check-phone', [CustomerCheckController::class, 'checkPhone']);

// 顧客向けサブスクリプション情報（トークンベース認証）
Route::get('customer/subscriptions-token', [\App\Http\Controllers\Api\CustomerSubscriptionController::class, 'getSubscriptions']);
Route::post('subscription/setup-session', [\App\Http\Controllers\Api\CustomerSubscriptionController::class, 'setupSession']);

// 顧客向け回数券情報（トークンベース認証）
Route::get('customer/tickets-token', [\App\Http\Controllers\CustomerTicketController::class, 'index']);
Route::get('customer/tickets/{ticketId}/history', [\App\Http\Controllers\CustomerTicketController::class, 'history']);

// 予約作成（認証不要）
// Public reservation functionality moved to web routes

// 顧客向けAPI（認証必須）
Route::middleware('auth:sanctum')->prefix('customer')->group(function () {
    // 予約管理
    Route::get('reservations', [ReservationController::class, 'customerReservations']);
    Route::post('reservations', [ReservationController::class, 'createReservation']); // 予約作成
    Route::get('reservations/{id}', [ReservationController::class, 'customerReservationDetail']);
    Route::post('reservations/{id}/cancel', [ReservationController::class, 'cancelReservation']);
    Route::post('reservations/{id}/change', [ReservationController::class, 'changeReservationDate']);
    Route::put('reservations/{id}', [ReservationController::class, 'updateReservation']);
    // プロフィール管理
    Route::get('profile', [CustomerController::class, 'show']);
    Route::put('profile', [CustomerController::class, 'update']);
    // カルテ管理
    Route::get('medical-records', [CustomerController::class, 'getMedicalRecords']);
    Route::post('reservation-context/medical-record', [CustomerController::class, 'createMedicalRecordContext']);
    Route::post('reservation-context/subscription', [CustomerController::class, 'createSubscriptionContext']);
    // 顧客画像
    Route::get('images', [CustomerController::class, 'getImages']);
    // 通知設定
    Route::get('notification-preferences', [NotificationPreferenceController::class, 'show']);
    Route::put('notification-preferences', [NotificationPreferenceController::class, 'update']);
    // サブスクリプション情報
    Route::get('subscriptions', [\App\Http\Controllers\Api\CustomerSubscriptionController::class, 'index']);
    Route::get('subscription', [\App\Http\Controllers\Api\CustomerSubscriptionController::class, 'show']);
});

// SMS配信停止（認証不要・トークンベース）
Route::post('unsubscribe-sms', [NotificationPreferenceController::class, 'unsubscribe']);

// LINE Webhook（店舗別）
Route::post('line/webhook/{store_code}', [\App\Http\Controllers\LineWebhookController::class, 'handle']);

// LINE連携API（認証不要・トークンベース）
Route::post('line/link', [LineLinkController::class, 'link']);
Route::get('line/status', [LineLinkController::class, 'status']);
Route::post('line/link-reservation', [LineLinkController::class, 'linkByReservation']);

// 予約番号から店舗情報取得（LIFF用）
Route::get('reservation/{reservationNumber}/store-info', [ReservationController::class, 'getStoreInfoByReservationNumber']);

// 店舗LIFF ID取得API
Route::get('stores/{store}/liff-id', [\App\Http\Controllers\Api\StoreLiffController::class, 'getLiffId']);

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

    // 予約管理の追加アクション
    Route::post('reservations/{id}/cancel', [ReservationController::class, 'adminCancelReservation']);
    Route::post('reservations/{id}/complete', [ReservationController::class, 'adminCompleteReservation']);
    Route::post('reservations/{id}/no-show', [ReservationController::class, 'adminNoShowReservation']);
    Route::post('reservations/{id}/restore', [ReservationController::class, 'adminRestoreReservation']);
    Route::post('reservations/{id}/move-to-sub', [ReservationController::class, 'adminMoveToSubLine']);
    Route::post('reservations/{id}/move-to-main', [ReservationController::class, 'adminMoveToMainLine']);
});