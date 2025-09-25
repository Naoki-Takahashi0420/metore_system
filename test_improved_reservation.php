<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Reservation;
use App\Models\Store;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\Shift;
use Carbon\Carbon;

// Laravelアプリケーションの初期化
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// テスト用のReservationインスタンスを作成（保存せず）
$store = Store::find(2);
$customer = Customer::first();
$menu = Menu::first();

echo "=== 修正後のテスト ===\n";
echo "店舗: {$store->name} (スタッフシフトモード: " . ($store->use_staff_assignment ? '有効' : '無効') . ")\n";
echo "営業時間: 13:00-22:00\n\n";

// 現在のシフト情報を表示
$today = Carbon::now()->format('Y-m-d');
$shifts = Shift::where('store_id', 2)
    ->whereDate('shift_date', $today)
    ->where('status', 'scheduled')
    ->get();

echo "=== 本日のシフト ===\n";
foreach ($shifts as $shift) {
    echo "  スタッフID {$shift->user_id}: {$shift->start_time} - {$shift->end_time}\n";
}
echo "\n";

// テストケース（指名なし）
$testCases = [
    ['start' => '10:00', 'end' => '11:00', 'staff_id' => null, 'description' => '指名なし：シフト時間内（9:00-14:00内）'],
    ['start' => '13:30', 'end' => '14:30', 'staff_id' => null, 'description' => '指名なし：シフト境界を跨ぐ'],
    ['start' => '15:00', 'end' => '16:30', 'staff_id' => null, 'description' => '指名なし：シフト後、営業時間内'],
    ['start' => '18:00', 'end' => '19:00', 'staff_id' => null, 'description' => '指名なし：夕方、営業時間内'],
    ['start' => '08:00', 'end' => '09:00', 'staff_id' => null, 'description' => '指名なし：シフト前、営業時間外'],
];

// スタッフ指名ありのテストケース
$staffTestCases = [
    ['start' => '10:00', 'end' => '11:00', 'staff_id' => 5, 'description' => 'スタッフ5指名：シフト時間内'],
    ['start' => '15:00', 'end' => '16:00', 'staff_id' => 5, 'description' => 'スタッフ5指名：シフト時間外'],
];

$allTests = array_merge($testCases, $staffTestCases);

foreach ($allTests as $index => $test) {
    echo "--- テストケース " . ($index + 1) . ": {$test['description']} ---\n";
    echo "予約時間: {$test['start']} - {$test['end']}\n";
    if ($test['staff_id']) {
        echo "指名スタッフID: {$test['staff_id']}\n";
    }

    // 予約オブジェクトを作成（保存はしない）
    $reservation = new Reservation();
    $reservation->store_id = $store->id;
    $reservation->customer_id = $customer->id;
    $reservation->menu_id = $menu->id;
    $reservation->reservation_date = Carbon::now()->format('Y-m-d');
    $reservation->start_time = $test['start'];
    $reservation->end_time = $test['end'];
    $reservation->guest_count = 1;
    $reservation->status = 'booked';
    $reservation->source = 'phone';
    $reservation->staff_id = $test['staff_id'];

    // checkAvailabilityを直接呼び出し
    try {
        $isAvailable = Reservation::checkAvailability($reservation);
        echo "結果: ✅ 予約可能\n";
    } catch (\Exception $e) {
        echo "結果: ❌ " . $e->getMessage() . "\n";
    }

    echo "\n";
}

echo "=== テスト完了 ===\n";