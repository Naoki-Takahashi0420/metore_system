<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->handleRequest(\Illuminate\Http\Request::capture());

use App\Models\Reservation;
use App\Models\Store;

// 店舗の設定確認
$store = Store::find(1);
echo "Store mode: " . ($store->use_staff_assignment ? 'Staff Shift' : 'Business Hours') . "\n";
echo "Capacity: " . $store->shift_based_capacity . "\n\n";

// テスト用の予約データ
$testReservation = new Reservation([
    'store_id' => 1,
    'customer_id' => 1,
    'menu_id' => 1,
    'reservation_date' => '2025-09-25',
    'start_time' => '16:00',
    'end_time' => '17:00',
    'guest_count' => 1,
    'status' => 'booked',
    'source' => 'phone',
    'staff_id' => null,  // スタッフ未指定
    'total_amount' => 0,
    'deposit_amount' => 0,
    'payment_method' => 'cash',
    'payment_status' => 'unpaid',
]);

echo "Testing checkAvailability...\n";
$available = Reservation::checkAvailability($testReservation);
echo "Available: " . ($available ? 'YES' : 'NO') . "\n\n";

if ($available) {
    echo "Trying to create reservation...\n";
    try {
        $testReservation->save();
        echo "Success! Reservation created with ID: " . $testReservation->id . "\n";
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "Time slot not available.\n";
}