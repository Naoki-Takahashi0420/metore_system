<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\PasswordResetController;

// トップページの処理（環境変数で制御可能）
Route::get('/', function () {
    // REDIRECT_TO_STORES=true の場合は店舗一覧にリダイレクト
    if (env('REDIRECT_TO_STORES', true)) {
        return redirect('/stores');
    }
    // それ以外はウェルカムページを表示
    return view('welcome');
});

// オリジナルのウェルカムページを別URLで保持
Route::get('/welcome', function () {
    return view('welcome');
});

// Health check endpoint for deployment verification
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now(),
        'version' => config('app.version', '1.0.0')
    ]);
});

// Store routes
Route::get('/stores', function () {
    return view('stores.index');
});

// 旧予約ルート（datetime, customer, confirmは新しいフローへリダイレクト）
// 日時選択ページ（新しいUIを使用）
Route::get('/reservation/datetime', [App\Http\Controllers\PublicReservationController::class, 'index'])->name('reservation.datetime');
Route::get('/reservation/customer', function () {
    // セッションから日時情報を確認
    if (!session('reservation_menu') || !session('reservation_datetime')) {
        return redirect('/reservation/menu');
    }
    return view('reservation.customer');
});
Route::get('/reservation/confirm', function () {
    // セッションから必要な情報を確認
    if (!session('reservation_menu') || !session('reservation_datetime') || !session('customer_data')) {
        return redirect('/reservation/menu');
    }
    return view('reservation.confirm');
});

// Public reservation routes
Route::get('/reservation/store', [App\Http\Controllers\PublicReservationController::class, 'selectStore'])->name('reservation.select-store');
Route::post('/reservation/store-selection', [App\Http\Controllers\PublicReservationController::class, 'storeStoreSelection'])->name('reservation.store-store');
Route::get('/reservation/menu', [App\Http\Controllers\PublicReservationController::class, 'selectMenu'])->name('reservation.menu');
Route::get('/reservation/menu/{store_id}', [App\Http\Controllers\PublicReservationController::class, 'selectMenuWithStore'])->name('reservation.menu-with-store');
Route::post('/reservation/select-menu', [App\Http\Controllers\PublicReservationController::class, 'storeMenu'])->name('reservation.select-menu');
Route::get('/reservation/upsell', [App\Http\Controllers\PublicReservationController::class, 'showUpsell'])->name('reservation.upsell');
// 予約トップページ（店舗選択に統一のため削除）
// Route::get('/reservation', function () {
//     return view('reservation.index');
// })->name('reservation.index');

// 予約は店舗選択から開始するため、リダイレクト
Route::get('/reservation', function () {
    return redirect('/stores');
})->name('reservation.index');

// 初回顧客用の予約フロー（店舗選択から開始）
Route::get('/reservation/new', [App\Http\Controllers\PublicReservationController::class, 'selectStore'])->name('reservation.store-select');
Route::post('/reservation/submit', [App\Http\Controllers\PublicReservationController::class, 'store'])->name('reservation.store');
Route::get('/reservation/complete/{reservationNumber}', [App\Http\Controllers\PublicReservationController::class, 'complete'])->name('reservation.complete');

// Customer routes
Route::prefix('customer')->group(function () {
    Route::get('/login', function () {
        return view('customer.login');
    });
    
    Route::get('/register', function () {
        return view('customer.register');
    });
    
    Route::get('/reservations', function () {
        return view('customer.reservations');
    });
    
    Route::get('/reservations/{id}', function ($id) {
        return view('customer.reservation-detail', compact('id'));
    });
    
    Route::get('/medical-records', function () {
        return view('customer.medical-records');
    });
});

// パスワードリセット用ルート
Route::get('/admin/password-reset', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
Route::post('/admin/password-reset', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
Route::get('/admin/password-reset/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/admin/password-reset/update', [PasswordResetController::class, 'reset'])->name('password.update');