<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\PasswordResetController;

// Health check endpoint for deployment verification
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now(),
        'version' => config('app.version', '1.0.0')
    ]);
});

// Debug: View logs (本番環境では削除すること)
Route::get('/debug/logs', function () {
    $logFile = storage_path('logs/laravel.log');
    if (!file_exists($logFile)) {
        return response('Log file not found', 404);
    }
    $lines = file($logFile);
    $last200 = array_slice($lines, -200);
    return response('<pre>' . implode('', $last200) . '</pre>');
});

// Basic認証ミドルウェアでサイト全体を保護（管理画面は除く）
Route::middleware(['auth.basic'])->group(function () {

// トップページは店舗選択画面へリダイレクト
Route::get('/', function (\Illuminate\Http\Request $request) {
    // 環境変数で切り替え可能
    if (env('SHOW_WELCOME_PAGE', false)) {
        return view('welcome');
    }
    // パラメータを引き継いでリダイレクト
    $params = [];
    if ($request->has('source')) $params['source'] = $request->get('source');
    if ($request->has('customer_id')) $params['customer_id'] = $request->get('customer_id');
    return redirect()->route('stores', $params);
});

// オリジナルのウェルカムページを別URLで保持
Route::get('/welcome', function () {
    return view('welcome');
});

// Store routes - パラメータベース対応
Route::get('/stores', [App\Http\Controllers\PublicReservationController::class, 'selectStore'])->name('stores');

// 古い予約フローのルートを削除済み（2025-08-27）
// 現在は○×形式のカレンダー（/reservation/calendar）を使用

// Public reservation routes - 新フロー（カテゴリー→時間→カレンダー）
// 店舗選択は /stores を使用（/reservation/store は削除済み）
Route::post('/reservation/store-selection', [App\Http\Controllers\PublicReservationController::class, 'storeStoreSelection'])->name('reservation.store-store');

// 新しい予約フロー
Route::get('/reservation/category', [App\Http\Controllers\PublicReservationController::class, 'selectCategory'])->name('reservation.select-category');
Route::match(['get', 'post'], '/reservation/time', [App\Http\Controllers\PublicReservationController::class, 'selectTime'])->name('reservation.select-time');
Route::get('/reservation/options/{menu}', [App\Http\Controllers\PublicReservationController::class, 'selectOptions'])->name('reservation.select-options');
Route::post('/reservation/store-options', [App\Http\Controllers\PublicReservationController::class, 'storeOptions'])->name('reservation.store-options');
Route::post('/reservation/store-menu', [App\Http\Controllers\PublicReservationController::class, 'storeMenu'])->name('reservation.store-menu');

// スタッフ選択
Route::get('/reservation/staff', [App\Http\Controllers\PublicReservationController::class, 'selectStaff'])->name('reservation.select-staff');
Route::post('/reservation/store-staff', [App\Http\Controllers\PublicReservationController::class, 'storeStaff'])->name('reservation.store-staff');

// 互換性保持用（旧ルート）
Route::get('/reservation/menu', [App\Http\Controllers\PublicReservationController::class, 'selectMenu'])->name('reservation.menu');
Route::get('/reservation/menu/{store_id}', [App\Http\Controllers\PublicReservationController::class, 'selectMenuWithStore'])->name('reservation.menu-with-store');
Route::post('/reservation/select-menu', [App\Http\Controllers\PublicReservationController::class, 'storeMenu'])->name('reservation.select-menu');
// 予約トップページは店舗選択にリダイレクト
Route::get('/reservation', function (\Illuminate\Http\Request $request) {
    $params = [];
    if ($request->has('source')) $params['source'] = $request->get('source');
    if ($request->has('customer_id')) $params['customer_id'] = $request->get('customer_id');
    return redirect()->route('stores', $params);
});

// 不足していたルート追加
Route::get('/reservation/select-store', function () {
    return redirect('/stores');
})->name('reservation.select-store');

// ○×形式のカレンダー表示（PublicReservationControllerのindexメソッド）
Route::get('/reservation/calendar', [App\Http\Controllers\PublicReservationController::class, 'index'])->name('reservation.index');


// サブスク予約準備（セッションに店舗とメニューを設定してカレンダーへ）
Route::post('/reservation/subscription-prepare', [App\Http\Controllers\PublicReservationController::class, 'prepareSubscriptionReservation'])->name('reservation.subscription-prepare');

// 予約変更準備（セッションに情報を保存してカレンダーへ）
Route::post('/reservation/prepare-change', [App\Http\Controllers\PublicReservationController::class, 'prepareChange'])->name('reservation.prepare-change');

// 予約フローはすべて店舗選択から開始
Route::post('/reservation/submit', [App\Http\Controllers\PublicReservationController::class, 'store'])->name('reservation.store');
Route::get('/reservation/complete/{reservationNumber}', [App\Http\Controllers\PublicReservationController::class, 'complete'])->name('reservation.complete');

// 顧客の最後に訪問した店舗を取得
Route::get('/reservation/last-visited-store', [App\Http\Controllers\PublicReservationController::class, 'getLastVisitedStore'])->name('reservation.last-visited-store');

// 可用性チェックAPI - api.phpに移動済み

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

    Route::get('/tickets', function () {
        return view('customer.tickets');
    })->name('customer.tickets');

    Route::get('/medical-records', function () {
        return view('customer.medical-records');
    });

    // 回数券
    Route::get('/tickets', [App\Http\Controllers\CustomerTicketController::class, 'show'])
        ->name('customer.tickets');

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

// 領収証関連ルート
Route::get('/receipt/reservation/{reservationId}', [App\Http\Controllers\ReceiptController::class, 'printFromReservation'])
    ->name('receipt.print-reservation')
    ->middleware(['auth.basic']);

Route::get('/receipt/reservation/{reservationId}/pdf', [App\Http\Controllers\ReceiptController::class, 'downloadPdf'])
    ->name('receipt.download-pdf')
    ->middleware(['auth.basic']);

// 予約空き状況API
Route::post('/api/reservations/availability', function() {
    $validated = request()->validate([
        'store_id' => 'required|exists:stores,id',
        'date' => 'required|date',
        'staff_id' => 'nullable|exists:users,id',
        'current_reservation_id' => 'nullable|exists:reservations,id',
    ]);

    $date = \Carbon\Carbon::parse($validated['date']);
    $events = [];

    // 既存の予約を取得
    $query = \App\Models\Reservation::where('store_id', $validated['store_id'])
        ->where('reservation_date', $date->format('Y-m-d'))
        ->whereNotIn('status', ['cancelled', 'canceled']);

    if (isset($validated['staff_id'])) {
        $query->where('staff_id', $validated['staff_id']);
    }

    $reservations = $query->with(['customer', 'menu'])->get();

    foreach ($reservations as $reservation) {
        $isCurrentReservation = isset($validated['current_reservation_id']) &&
                               $reservation->id == $validated['current_reservation_id'];

        $events[] = [
            'id' => $reservation->id,
            'title' => $isCurrentReservation ? '現在の予約' :
                      ($reservation->customer->last_name . ' ' . $reservation->customer->first_name),
            'start' => $date->format('Y-m-d') . 'T' . $reservation->start_time,
            'end' => $date->format('Y-m-d') . 'T' . $reservation->end_time,
            'className' => $isCurrentReservation ? 'current-reservation' : 'other-reservation',
            'editable' => false,
        ];
    }

    return response()->json(['events' => $events]);
})->middleware(['auth'])->name('api.reservations.availability');

// 店舗営業時間API
Route::get('/api/stores/{store}/business-hours', function(\App\Models\Store $store) {
    $businessHours = [];
    $hours = $store->businessHours()->where('is_open', true)->get();

    foreach ($hours as $hour) {
        $businessHours[] = [
            'daysOfWeek' => [$hour->day_of_week],
            'startTime' => $hour->open_time,
            'endTime' => $hour->close_time,
        ];
    }

    return response()->json(['businessHours' => $businessHours]);
})->middleware(['auth'])->name('api.stores.business-hours');

// 予約日時変更用API（カレンダーのドラッグ&ドロップ用）
Route::post('/admin/reservations/update-datetime', function() {
    $validated = request()->validate([
        'id' => 'required|exists:reservations,id',
        'date' => 'required|date',
        'start_time' => 'required|date_format:H:i',
        'end_time' => 'required|date_format:H:i',
    ]);

    try {
        $reservation = \App\Models\Reservation::findOrFail($validated['id']);

        // 権限チェック
        $user = auth()->user();
        if (!$user->hasRole('super_admin')) {
            if ($user->hasRole('owner')) {
                $manageableStoreIds = $user->manageableStores()->pluck('stores.id');
                if (!$manageableStoreIds->contains($reservation->store_id)) {
                    return response()->json(['success' => false, 'message' => '権限がありません'], 403);
                }
            } elseif ($user->hasRole(['manager', 'staff'])) {
                if ($user->store_id !== $reservation->store_id) {
                    return response()->json(['success' => false, 'message' => '権限がありません'], 403);
                }
            }
        }

        // 重複チェック（同じスタッフ、同じ日時に既存の予約がないか）
        if ($reservation->staff_id) {
            $existingReservation = \App\Models\Reservation::where('id', '!=', $reservation->id)
                ->where('staff_id', $reservation->staff_id)
                ->where('reservation_date', $validated['date'])
                ->whereNotIn('status', ['cancelled', 'canceled'])
                ->where(function($query) use ($validated) {
                    $query->where(function($q) use ($validated) {
                        // 新しい時間帯の開始時刻が既存の予約時間内にある
                        $q->where('start_time', '<=', $validated['start_time'])
                          ->where('end_time', '>', $validated['start_time']);
                    })->orWhere(function($q) use ($validated) {
                        // 新しい時間帯の終了時刻が既存の予約時間内にある
                        $q->where('start_time', '<', $validated['end_time'])
                          ->where('end_time', '>=', $validated['end_time']);
                    })->orWhere(function($q) use ($validated) {
                        // 新しい時間帯が既存の予約を完全に含む
                        $q->where('start_time', '>=', $validated['start_time'])
                          ->where('end_time', '<=', $validated['end_time']);
                    });
                })
                ->exists();

            if ($existingReservation) {
                return response()->json([
                    'success' => false,
                    'message' => '選択された時間帯は既に予約が入っています'
                ], 400);
            }
        }

        // 営業時間チェック
        $store = $reservation->store;
        $dayOfWeek = \Carbon\Carbon::parse($validated['date'])->dayOfWeek;
        $businessHour = $store->businessHours()->where('day_of_week', $dayOfWeek)->first();

        if (!$businessHour || !$businessHour->is_open) {
            return response()->json([
                'success' => false,
                'message' => 'この日は営業していません'
            ], 400);
        }

        if ($validated['start_time'] < $businessHour->open_time ||
            $validated['end_time'] > $businessHour->close_time) {
            return response()->json([
                'success' => false,
                'message' => '営業時間外です'
            ], 400);
        }

        // 更新
        $reservation->update([
            'reservation_date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
        ]);

        return response()->json([
            'success' => true,
            'message' => '予約日時を変更しました'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'エラーが発生しました: ' . $e->getMessage()
        ], 500);
    }
})->middleware(['auth', 'auth.basic'])->name('admin.reservations.update-datetime');

// 管理画面用の予約日程変更ページ
Route::get('/admin/reservations/{reservation}/reschedule', [App\Http\Controllers\Admin\ReservationRescheduleController::class, 'show'])
    ->name('admin.reservations.reschedule')
    ->middleware(['auth', 'auth.basic']);
Route::post('/admin/reservations/{reservation}/reschedule', [App\Http\Controllers\Admin\ReservationRescheduleController::class, 'update'])
    ->name('admin.reservations.reschedule.update')
    ->middleware(['auth', 'auth.basic']);

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

// LINE連携ページ（Basic認証不要）
Route::get('/line/link', function () {
    return view('line.link');
})->name('line.link');

// テスト用ルート（開発環境のみ）
if (app()->environment('local')) {
    require __DIR__.'/test.php';
}// テスト用エンドポイント
Route::get('/test/subscription/{phone}', [\App\Http\Controllers\Api\TestSubscriptionController::class, 'getByPhone']);
