<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\PasswordResetController;

// Basic認証ミドルウェアでサイト全体を保護（管理画面は除く）
Route::middleware(['auth.basic'])->group(function () {

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

// 古い予約フローのルートを削除済み（2025-08-27）
// 現在は○×形式のカレンダー（/reservation/calendar）を使用

// Public reservation routes - 新フロー（カテゴリー→時間→カレンダー）
Route::get('/reservation/store', [App\Http\Controllers\PublicReservationController::class, 'selectStore'])->name('reservation.select-store');
Route::post('/reservation/store-selection', [App\Http\Controllers\PublicReservationController::class, 'storeStoreSelection'])->name('reservation.store-store');

// 新しい予約フロー
Route::get('/reservation/category', [App\Http\Controllers\PublicReservationController::class, 'selectCategory'])->name('reservation.select-category');
Route::post('/reservation/time', [App\Http\Controllers\PublicReservationController::class, 'selectTime'])->name('reservation.select-time');
Route::get('/reservation/options/{menu}', [App\Http\Controllers\PublicReservationController::class, 'selectOptions'])->name('reservation.select-options');
Route::post('/reservation/store-options', [App\Http\Controllers\PublicReservationController::class, 'storeOptions'])->name('reservation.store-options');
Route::post('/reservation/store-menu', [App\Http\Controllers\PublicReservationController::class, 'storeMenu'])->name('reservation.store-menu');

// 互換性保持用（旧ルート）
Route::get('/reservation/menu', [App\Http\Controllers\PublicReservationController::class, 'selectMenu'])->name('reservation.menu');
Route::get('/reservation/menu/{store_id}', [App\Http\Controllers\PublicReservationController::class, 'selectMenuWithStore'])->name('reservation.menu-with-store');
Route::post('/reservation/select-menu', [App\Http\Controllers\PublicReservationController::class, 'storeMenu'])->name('reservation.select-menu');
// 予約トップページは店舗選択にリダイレクト
Route::get('/reservation', function () {
    return redirect('/stores');
});

// ○×形式のカレンダー表示（PublicReservationControllerのindexメソッド）
Route::get('/reservation/calendar', [App\Http\Controllers\PublicReservationController::class, 'index'])->name('reservation.index');

// 予約フローはすべて店舗選択から開始
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
    
    Route::get('/dashboard', function () {
        // モダン版を表示（メルカリ風UI）
        return view('customer.dashboard-modern');
    });
    
    // 旧ダッシュボード（スタンダード版）
    Route::get('/dashboard-standard', function () {
        return view('customer.dashboard-new');
    });
    
    // モダンダッシュボード（メルカリ風）
    Route::get('/dashboard-modern', function () {
        return view('customer.dashboard-modern');
    });
    
    Route::get('/reservations', function () {
        return view('customer.dashboard');
    });
    
    Route::get('/reservations/{id}', function ($id) {
        return view('customer.reservation-detail', compact('id'));
    });
    
    Route::get('/medical-records', function () {
        return view('customer.medical-records');
    });
    
    // 視力推移表示（コントローラーメソッドがまだ実装されていない場合はビューを直接返す）
    Route::get('/customer/vision-progress', function () {
        return view('customer.vision-progress');
    })->name('customer.vision-progress');
    
    // 旧ルート（互換性のため残す）
    Route::get('/customer/{customer}/vision-progress', [App\Http\Controllers\MedicalRecordController::class, 'showVisionProgress'])
        ->name('customer.vision-progress-old');
    
    // カルテ印刷
    Route::get('/medical-record/{record}/print', [App\Http\Controllers\MedicalRecordController::class, 'print'])
        ->name('medical-record.print');
    
    // スタッフ用シフト確認
    Route::prefix('staff')->name('staff.')->group(function () {
        Route::get('/shifts', [App\Http\Controllers\StaffShiftController::class, 'index'])->name('shifts.index');
        Route::get('/shifts/{id}', [App\Http\Controllers\StaffShiftController::class, 'show'])->name('shifts.show');
    });
});

// 管理画面用ルート
Route::post('/admin/menu-categories/update-order', [App\Http\Controllers\Admin\MenuCategoryController::class, 'updateOrder'])
    ->name('admin.menu-categories.update-order');

// パスワードリセット用ルート
Route::get('/admin/password-reset', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
Route::post('/admin/password-reset', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
Route::get('/admin/password-reset/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/admin/password-reset/update', [PasswordResetController::class, 'reset'])->name('password.update');

}); // Basic認証グループの終了

// テスト用ルート（開発環境のみ）
if (app()->environment('local')) {
    require __DIR__.'/test.php';
}