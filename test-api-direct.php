<?php
// Laravel bootstrapを読み込む
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// 顧客を取得
$customer = \App\Models\Customer::where('phone', '08033372305')->first();
if (!$customer) {
    die("Customer not found\n");
}

echo "Customer: {$customer->last_name} {$customer->first_name} (ID: {$customer->id})\n\n";

// 予約を取得（APIと同じ方法）
$reservations = \App\Models\Reservation::where('customer_id', $customer->id)
    ->with(['store', 'menu', 'staff'])
    ->orderBy('reservation_date', 'desc')
    ->orderBy('start_time', 'desc')
    ->get();

echo "Total reservations: " . $reservations->count() . "\n\n";

// 各予約の詳細を表示
foreach ($reservations as $i => $reservation) {
    echo "Reservation " . ($i + 1) . ":\n";
    echo "  Date: {$reservation->reservation_date}\n";
    echo "  Time: {$reservation->start_time} - {$reservation->end_time}\n";
    echo "  Status: {$reservation->status}\n";
    echo "  Store: " . ($reservation->store ? $reservation->store->name : 'NULL') . "\n";
    echo "  Menu: " . ($reservation->menu ? $reservation->menu->name : 'NULL') . "\n";
    echo "  Menu ID: {$reservation->menu_id}\n";
    echo "  Store ID: {$reservation->store_id}\n";
    echo "  Total: {$reservation->total_amount}\n";
    echo "\n";
}

// JSON形式でも出力
echo "\n=== JSON Response (like API) ===\n";
$jsonData = [
    'message' => '予約履歴を取得しました',
    'data' => $reservations->toArray()
];
echo json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);