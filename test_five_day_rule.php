<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use App\Models\Customer;
use Illuminate\Support\Facades\Hash;

echo "=== 5日間ルールのテスト ===\n\n";

// 須藤亜希子さんのデータを確認
$customer = Customer::find(2813);

if (!$customer) {
    echo "❌ 顧客が見つかりません (ID: 2813)\n";
    exit(1);
}

echo "顧客情報:\n";
echo "  ID: {$customer->id}\n";
echo "  名前: {$customer->full_name}\n";
echo "  電話: {$customer->phone}\n";
echo "  店舗ID: {$customer->store_id}\n\n";

// トークン生成
$token = $customer->createToken('test-token')->plainTextToken;
echo "✅ 認証トークン生成: " . substr($token, 0, 20) . "...\n\n";

// 既存予約を確認
$reservations = $customer->reservations()
    ->whereNotIn('status', ['cancelled', 'canceled'])
    ->orderBy('reservation_date', 'desc')
    ->get();

echo "既存予約:\n";
foreach ($reservations as $reservation) {
    echo "  - {$reservation->reservation_date->format('Y-m-d')} {$reservation->start_time} ({$reservation->status})\n";
}
echo "\n";

// カレンダーAPIをテスト（店舗1、メニュー93、10月15日から）
$startDate = '2025-10-15';
$storeId = 1;
$menuId = 93;

echo "カレンダーAPIテスト:\n";
echo "  店舗ID: {$storeId}\n";
echo "  メニューID: {$menuId}\n";
echo "  開始日: {$startDate}\n\n";

// カレンダー表示APIを呼び出し
$url = "http://localhost:8000/reservation/calendar?store_id={$storeId}&menu_id={$menuId}&start_date={$startDate}";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "APIレスポンス: HTTP {$httpCode}\n";

if ($httpCode === 200) {
    echo "✅ カレンダー表示成功\n";
    echo "※ログを確認して customer_id が正しく取得されているか確認してください\n";
} else {
    echo "❌ カレンダー表示失敗\n";
    echo "レスポンス: " . substr($response, 0, 200) . "...\n";
}

echo "\n=== テスト完了 ===\n";
echo "Laravel のログ (storage/logs/laravel.log) を確認してください\n";
echo "  'API認証から顧客ID取得（マイページ）' のログが出力されているはずです\n";
