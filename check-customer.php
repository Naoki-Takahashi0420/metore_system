<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Customer;

// 電話番号で顧客を検索
$phoneNumber = '08033372305';
echo "=== 顧客検索: {$phoneNumber} ===\n\n";

$customer = Customer::where('phone_number', $phoneNumber)->first();

if ($customer) {
    echo "✅ 顧客が見つかりました:\n";
    echo "   ID: {$customer->id}\n";
    echo "   名前: {$customer->last_name} {$customer->first_name}\n";
    echo "   電話: {$customer->phone_number}\n";
    echo "   店舗ID: {$customer->store_id}\n";
    echo "   Email: {$customer->email}\n";
} else {
    echo "❌ 顧客が見つかりません\n\n";
    echo "=== 登録されている顧客一覧（最新10件） ===\n";
    $customers = Customer::orderBy('id', 'desc')->limit(10)->get();
    foreach ($customers as $c) {
        echo "ID: {$c->id} | {$c->last_name} {$c->first_name} | {$c->phone_number}\n";
    }
}
