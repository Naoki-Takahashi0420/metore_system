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

$store = Store::find(2);
$customer = Customer::first();
$menu = Menu::first();
$testDate = Carbon::now()->format('Y-m-d');

// 既存の13:30の予約を削除
Reservation::where('store_id', 2)
    ->whereDate('reservation_date', $testDate)
    ->whereTime('start_time', '13:30')
    ->delete();

echo "=== 連続予約テスト ===\n";
echo "13:30-14:30の時間帯に2つの未指定予約を作成テスト\n\n";

// 1つ目の予約を作成
echo "--- 1つ目の予約作成 ---\n";
try {
    $reservation1 = Reservation::create([
        'reservation_number' => 'TEST1_' . time(),
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'menu_id' => $menu->id,
        'reservation_date' => $testDate,
        'start_time' => '13:30',
        'end_time' => '14:30',
        'guest_count' => 1,
        'status' => 'booked',
        'source' => 'phone',
        'staff_id' => null, // 未指定
    ]);
    echo "✅ 1つ目の予約作成成功: {$reservation1->reservation_number}\n";
    echo "   スタッフID: " . ($reservation1->staff_id ?: 'null') . "\n";
} catch (\Exception $e) {
    echo "❌ 1つ目の予約作成失敗: " . $e->getMessage() . "\n";
}

echo "\n--- 2つ目の予約作成 ---\n";
try {
    $reservation2 = Reservation::create([
        'reservation_number' => 'TEST2_' . time(),
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'menu_id' => $menu->id,
        'reservation_date' => $testDate,
        'start_time' => '13:30',
        'end_time' => '14:30',
        'guest_count' => 1,
        'status' => 'booked',
        'source' => 'phone',
        'staff_id' => null, // 未指定
    ]);
    echo "✅ 2つ目の予約作成成功: {$reservation2->reservation_number}\n";
    echo "   スタッフID: " . ($reservation2->staff_id ?: 'null') . "\n";
} catch (\Exception $e) {
    echo "❌ 2つ目の予約作成失敗: " . $e->getMessage() . "\n";
}

// 現在の予約状況を確認
echo "\n--- 現在の13:30予約状況 ---\n";
$reservations = Reservation::where('store_id', 2)
    ->whereDate('reservation_date', $testDate)
    ->whereTime('start_time', '13:30')
    ->get();

foreach ($reservations as $r) {
    echo "予約ID: {$r->id}, スタッフID: " . ($r->staff_id ?: 'null') . ", ステータス: {$r->status}\n";
}

echo "\nテスト完了\n";