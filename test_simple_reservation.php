<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Reservation;
use App\Models\Store;
use App\Models\Customer;
use App\Models\Menu;
use Carbon\Carbon;

// Laravelアプリケーションの初期化
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// データ準備
$store = Store::find(2);
$customer = Customer::first();
$menu = Menu::first();
$testDate = Carbon::now()->format('Y-m-d');
$startTime = '15:00';
$endTime = '16:30';

echo "テスト開始\n";
echo "店舗: {$store->name}\n";
echo "顧客: {$customer->name}\n";
echo "日付: {$testDate}\n";
echo "時間: {$startTime} - {$endTime}\n\n";

// 予約データ
$reservationData = [
    'reservation_number' => 'TEST' . time(),
    'store_id' => $store->id,
    'customer_id' => $customer->id,
    'menu_id' => $menu->id,
    'reservation_date' => $testDate,
    'start_time' => $startTime,
    'end_time' => $endTime,
    'guest_count' => 1,
    'status' => 'booked',
    'source' => 'phone',
    'total_amount' => $menu->price ?? 0,
    'deposit_amount' => 0,
    'payment_method' => 'cash',
    'payment_status' => 'unpaid',
];

// スタッフシフトモードでない場合はline_typeとseat_numberを設定
if (!$store->use_staff_assignment) {
    $reservationData['line_type'] = 'main';
    $reservationData['seat_number'] = null; // 自動割り当て
    $reservationData['is_sub'] = false;
}

echo "予約作成中...\n";
try {
    $reservation = Reservation::create($reservationData);
    echo "✅ 予約作成成功！\n";
    echo "予約番号: {$reservation->reservation_number}\n";
    echo "席番号: {$reservation->seat_number}\n";
    echo "ライン番号: {$reservation->line_number}\n";
    echo "ラインタイプ: {$reservation->line_type}\n";
} catch (\Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
    echo "スタックトレース:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\nテスト完了\n";