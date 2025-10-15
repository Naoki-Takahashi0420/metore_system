<?php

// 須藤亜希子さん（ID: 2813）のテスト

// 1. トークン生成
echo "=== トークン生成 ===\n";
echo "php artisan tinker で以下を実行してください:\n\n";
echo "\$customer = App\\Models\\Customer::find(2813);\n";
echo "\$token = \$customer->createToken('test-token')->plainTextToken;\n";
echo "echo \$token;\n\n";

// 2. カレンダーAPI呼び出し
$token = readline("生成されたトークンを入力してください: ");

echo "\n=== カレンダーAPI呼び出し ===\n";
$url = "http://localhost:8000/reservation/calendar?store_id=1&menu_id=93&start_date=2025-10-15";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTPステータス: {$httpCode}\n";

if ($httpCode === 200) {
    echo "✅ API呼び出し成功\n\n";
    echo "=== ログ確認 ===\n";
    echo "storage/logs/laravel.log を確認してください\n";
    echo "「【優先1】パラメータベース：既存顧客の顧客ID設定」または\n";
    echo "「【優先2】API認証から顧客ID取得（マイページ）」のログが\n";
    echo "customer_id: 2813 で出力されているはずです\n";
} else {
    echo "❌ API呼び出し失敗\n";
    echo "レスポンス: " . substr($response, 0, 500) . "\n";
}
