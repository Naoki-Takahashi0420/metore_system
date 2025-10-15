#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Customer;
use App\Services\ReservationContextService;
use Illuminate\Support\Facades\Log;

echo "=== 須藤亜希子さん（ID: 2813）の5日間ルールテスト ===\n\n";

// Step 1: 顧客情報を取得
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

// Step 2: 既存予約を確認
$reservations = $customer->reservations()
    ->whereNotIn('status', ['cancelled', 'canceled'])
    ->orderBy('reservation_date', 'desc')
    ->get();

echo "既存予約:\n";
if ($reservations->isEmpty()) {
    echo "  (予約なし)\n";
} else {
    foreach ($reservations as $reservation) {
        echo "  - {$reservation->reservation_date->format('Y-m-d')} {$reservation->start_time} ({$reservation->status})\n";
    }
}
echo "\n";

// Step 3: トークン生成
$token = $customer->createToken('test-5day-rule')->plainTextToken;
echo "✅ 認証トークン生成: " . substr($token, 0, 20) . "...\n\n";

// Step 4: コンテキスト作成（マイページからの通常予約をシミュレート）
$contextService = new ReservationContextService();
$context = [
    'source' => 'mypage',
    'type' => 'normal',
    'customer_id' => $customer->id,
    'is_existing_customer' => true,
    'menu_id' => 93,  // テスト用メニューID
    'store_id' => 1,  // 店舗ID
    'option_ids' => [],
];

$encryptedContext = $contextService->encryptContext($context);
echo "✅ コンテキスト作成完了\n";
echo "  source: {$context['source']}\n";
echo "  type: {$context['type']}\n";
echo "  customer_id: {$context['customer_id']}\n";
echo "  menu_id: {$context['menu_id']}\n";
echo "  store_id: {$context['store_id']}\n\n";

// Step 5: ログファイルをクリア
file_put_contents(storage_path('logs/laravel.log'), '');
echo "✅ ログファイルをクリアしました\n\n";

// Step 6: カレンダーAPIを呼び出し
$url = "http://localhost:8000/reservation/calendar?ctx=" . urlencode($encryptedContext);
echo "カレンダーAPIテスト:\n";
echo "  URL: $url\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: text/html',
    'Cookie: XSRF-TOKEN=test; laravel_session=test',
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "APIレスポンス: HTTP {$httpCode}\n\n";

if ($httpCode === 200) {
    echo "✅ カレンダー表示成功\n\n";

    // ログを確認
    $logContent = file_get_contents(storage_path('logs/laravel.log'));

    echo "=== ログ確認 ===\n";

    // 優先度ログを確認
    if (strpos($logContent, '【優先1】') !== false) {
        echo "✅ Context経由で顧客ID取得\n";
        preg_match_all('/\【優先1】.*/', $logContent, $matches);
        foreach ($matches[0] as $match) {
            echo "  $match\n";
        }
    } elseif (strpos($logContent, '【優先2】') !== false) {
        echo "✅ API認証経由で顧客ID取得\n";
        preg_match_all('/\【優先2】.*/', $logContent, $matches);
        foreach ($matches[0] as $match) {
            echo "  $match\n";
        }
    } elseif (strpos($logContent, '【優先3】') !== false) {
        echo "✅ Session経由で顧客ID取得\n";
        preg_match_all('/\【優先3】.*/', $logContent, $matches);
        foreach ($matches[0] as $match) {
            echo "  $match\n";
        }
    } else {
        echo "⚠️  優先度ログが見つかりません\n";
    }

    echo "\n";

    // 5日間ルール適用ログを確認
    if (strpos($logContent, '5日間隔') !== false) {
        echo "✅ 5日間ルール適用確認:\n";
        preg_match_all('/.*5日間隔.*/', $logContent, $matches);
        foreach (array_slice($matches[0], 0, 5) as $match) {
            echo "  $match\n";
        }
    } else {
        echo "⚠️  5日間ルールのログが見つかりません\n";
    }

    echo "\n=== 完全なログを確認する場合 ===\n";
    echo "tail -100 storage/logs/laravel.log\n";
} else {
    echo "❌ カレンダー表示失敗\n";
    echo "レスポンス: " . substr($response, 0, 500) . "...\n";
}

echo "\n=== テスト完了 ===\n";
