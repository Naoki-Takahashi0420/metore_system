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
// 店舗選択は /stores を使用（/reservation/store は削除済み）
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

// サブスク予約準備（セッションに店舗とメニューを設定してカレンダーへ）
Route::post('/reservation/subscription-prepare', [App\Http\Controllers\PublicReservationController::class, 'prepareSubscriptionReservation'])->name('reservation.subscription-prepare');

// 予約変更準備（セッションに情報を保存してカレンダーへ）
Route::post('/reservation/prepare-change', [App\Http\Controllers\PublicReservationController::class, 'prepareChange'])->name('reservation.prepare-change');

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
        return view('customer.dashboard');
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
    
    // サブスク専用予約
    Route::get('/subscription-booking', function () {
        return view('reservation.subscription-booking');
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

// 勤怠レポートPDF出力用ルート
Route::get('/admin/attendance-report/pdf', function() {
    $storeId = request('store');
    $year = request('year');
    $month = request('month');
    
    $user = auth()->user();
    
    // アクセス可能な店舗を取得
    if ($user->hasRole('super_admin')) {
        $stores = \App\Models\Store::where('is_active', true)->get();
    } elseif ($user->hasRole('owner')) {
        $stores = $user->manageableStores()->get();
    } else {
        $stores = $user->store ? collect([$user->store]) : collect();
    }
    
    // AttendanceReportページのインスタンスを作成してレポート生成
    $report = new \App\Filament\Pages\AttendanceReport();
    $report->stores = $stores;
    $report->selectedStore = $storeId ?: $stores->first()?->id;
    $report->selectedYear = $year ?: now()->year;
    $report->selectedMonth = $month ?: now()->month;
    $report->generateReport();
    
    // シンプルなHTML出力（印刷用）
    $html = view('reports.attendance-pdf', [
        'reportData' => $report->reportData,
        'staffSummary' => $report->staffSummary,
        'dailySummary' => $report->dailySummary,
        'patternAnalysis' => $report->patternAnalysis
    ])->render();
    
    return response($html)
        ->header('Content-Type', 'text/html; charset=UTF-8');
})->name('attendance-report.pdf')->middleware('auth');

// パスワードリセット用ルート
Route::get('/admin/password-reset', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
Route::post('/admin/password-reset', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
Route::get('/admin/password-reset/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/admin/password-reset/update', [PasswordResetController::class, 'reset'])->name('password.update');

}); // Basic認証グループの終了

// テスト用ルート（開発環境のみ）
if (app()->environment('local')) {
    require __DIR__.'/test.php';
}// テスト用エンドポイント
Route::get('/test/subscription/{phone}', [\App\Http\Controllers\Api\TestSubscriptionController::class, 'getByPhone']);
