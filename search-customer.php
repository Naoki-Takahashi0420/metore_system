<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Customer;

// いろいろな形式で検索
$patterns = [
    '08033372305',
    '080-3337-2305',
    '080 3337 2305',
    '080 - 3337 - 2305',
];

echo "=== 顧客検索：08033372305（複数形式） ===\n\n";

foreach ($patterns as $pattern) {
    echo "検索中: {$pattern}\n";
    $customer = Customer::where('phone_number', $pattern)->first();
    if ($customer) {
        echo "✅ 見つかりました！\n";
        echo "   ID: {$customer->id}\n";
        echo "   名前: {$customer->last_name} {$customer->first_name}\n";
        echo "   電話: {$customer->phone_number}\n";
        echo "   店舗ID: {$customer->store_id}\n\n";
        exit(0);
    }
}

echo "\n❌ いずれの形式でも見つかりませんでした\n\n";

// 部分一致で検索
echo "=== 部分一致検索（'3337'を含む） ===\n";
$customers = Customer::where('phone_number', 'like', '%3337%')->get();

if ($customers->count() > 0) {
    foreach ($customers as $c) {
        echo "ID: {$c->id} | {$c->last_name} {$c->first_name} | {$c->phone_number}\n";
    }
} else {
    echo "該当なし\n";
}

echo "\n=== 電話番号が設定されている顧客（最新20件） ===\n";
$customersWithPhone = Customer::whereNotNull('phone_number')
    ->where('phone_number', '!=', '')
    ->orderBy('id', 'desc')
    ->limit(20)
    ->get();

foreach ($customersWithPhone as $c) {
    echo "ID: {$c->id} | {$c->last_name} {$c->first_name} | {$c->phone_number}\n";
}
