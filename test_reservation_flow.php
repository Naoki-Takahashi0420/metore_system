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

// テスト用のReservationインスタンスを作成（保存せず）
$store = Store::find(2);
$customer = Customer::first();
$menu = Menu::first();

echo "=== テストシナリオ ===\n";
echo "店舗: {$store->name} (スタッフシフトモード: " . ($store->use_staff_assignment ? '有効' : '無効') . ")\n";
echo "営業時間: 13:00-22:00\n";
echo "既存シフト: 09:00-14:00\n\n";

// テストケース
$testCases = [
    ['start' => '10:00', 'end' => '11:00', 'description' => 'シフト時間内（9:00-14:00内）'],
    ['start' => '13:30', 'end' => '14:00', 'description' => 'シフト時間の終わり頃'],
    ['start' => '15:00', 'end' => '16:30', 'description' => 'シフト後、営業時間内'],
    ['start' => '18:00', 'end' => '19:00', 'description' => '夕方、営業時間内'],
    ['start' => '08:00', 'end' => '09:00', 'description' => 'シフト前、営業時間外'],
];

foreach ($testCases as $index => $test) {
    echo "--- テストケース " . ($index + 1) . ": {$test['description']} ---\n";
    echo "予約時間: {$test['start']} - {$test['end']}\n";

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

    // checkAvailabilityを直接呼び出し
    try {
        $isAvailable = Reservation::checkAvailability($reservation);
        echo "結果: " . ($isAvailable ? "✅ 予約可能" : "❌ 予約不可") . "\n";

        // checkStaffAvailabilityの内部動作も確認
        if ($store->use_staff_assignment) {
            $reflection = new ReflectionClass(Reservation::class);
            $method = $reflection->getMethod('checkStaffAvailability');
            $method->setAccessible(true);
            $staffAvailable = $method->invoke(null, $store, $reservation);
            echo "  スタッフ利用可能: " . ($staffAvailable ? "はい" : "いいえ") . "\n";
        }
    } catch (\Exception $e) {
        echo "エラー: " . $e->getMessage() . "\n";
    }

    echo "\n";
}