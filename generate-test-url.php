<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$customer = App\Models\Customer::find(2);
$menu = App\Models\Menu::where('name', '指名のみ')->first();
$store = App\Models\Store::find(1);

if (!$customer || !$menu || !$store) {
    echo 'データが見つかりません' . PHP_EOL;
    echo 'Customer: ' . ($customer ? $customer->id : 'なし') . PHP_EOL;
    echo 'Menu: ' . ($menu ? $menu->id . ' - ' . $menu->name : 'なし') . PHP_EOL;
    echo 'Store: ' . ($store ? $store->id . ' - ' . $store->name : 'なし') . PHP_EOL;
    exit(1);
}

echo 'Customer: ' . $customer->id . ' (' . $customer->phone . ')' . PHP_EOL;
echo 'Menu: ' . $menu->id . ' (' . $menu->name . ')' . PHP_EOL;
echo 'Store: ' . $store->id . ' (' . $store->name . ')' . PHP_EOL;
echo 'Menu requires_staff: ' . ($menu->requires_staff ? 'true' : 'false') . PHP_EOL;
echo 'Store use_staff_assignment: ' . ($store->use_staff_assignment ? 'true' : 'false') . PHP_EOL;

$context = [
    'customer_id' => $customer->id,
    'menu_id' => $menu->id,
    'store_id' => $store->id,
    'is_subscription' => false,
    'timestamp' => time()
];

$contextService = new App\Services\ReservationContextService();
$encryptedContext = $contextService->encryptContext($context);

echo PHP_EOL . 'テストURL:' . PHP_EOL;
echo 'http://localhost:8000/reservation/calendar?ctx=' . urlencode($encryptedContext) . PHP_EOL;
