<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$tomorrow = now()->addDay()->format('Y-m-d');
$customer = App\Models\Customer::first();
$menu = App\Models\Menu::where('store_id', 1)->first();

if (!$customer || !$menu) {
    echo "Error: Customer or Menu not found\n";
    exit(1);
}

echo "顧客ID: " . $customer->id . " (" . $customer->name . ")\n";
echo "メニューID: " . $menu->id . " (" . $menu->name . ")\n";
echo "予約日時: " . $tomorrow . " 10:00-11:00\n\n";

// シナリオ1: 銀座本店（2席）に同じ時間帯で2件の予約を作成（両方line_number=1）
$res1 = App\Models\Reservation::create([
    'customer_id' => $customer->id,
    'store_id' => 1,
    'menu_id' => $menu->id,
    'reservation_date' => $tomorrow,
    'start_time' => $tomorrow . ' 10:00:00',
    'end_time' => $tomorrow . ' 11:00:00',
    'line_number' => 1,
    'status' => 'confirmed',
    'total_amount' => 5000,
    'is_first_visit' => false,
]);

$res2 = App\Models\Reservation::create([
    'customer_id' => $customer->id,
    'store_id' => 1,
    'menu_id' => $menu->id,
    'reservation_date' => $tomorrow,
    'start_time' => $tomorrow . ' 10:00:00',
    'end_time' => $tomorrow . ' 11:00:00',
    'line_number' => 1,
    'status' => 'confirmed',
    'total_amount' => 5000,
    'is_first_visit' => false,
]);

echo "✅ テストデータ作成完了\n";
echo "銀座本店（2席）に同じ時間帯で2件の予約を作成（両方line_number=1）\n";
echo "予約1 ID: " . $res1->id . "\n";
echo "予約2 ID: " . $res2->id . "\n";
echo "\n";
echo "これにより、旧バグでは「1席使用中」と誤判定されダブルブッキングが可能になっていました。\n";
echo "修正後は「2席とも使用中」と正しく判定され、予約不可になるはずです。\n";
