<?php
require_once 'vendor/autoload.php';

// Laravelアプリケーションのブートストラップ
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== 電話番号フォーマット詳細テスト ===\n\n";

$phone = '08033372305';
echo "Original: $phone\n";

// Step 1: ハイフン、スペース、その他の記号を除去
$phone = preg_replace('/[^0-9+]/', '', $phone);
echo "Step 1 (after cleanup): $phone\n";

// Step 2: すでに+81で始まる場合はそのまま
if (strpos($phone, '+81') === 0) {
    echo "Step 2: Already starts with +81\n";
} else {
    echo "Step 2: Does not start with +81\n";
}

// Step 3: +8180, +8190などの場合（080, 090の0が残っている）
if (preg_match('/^\+81[789]0/', $phone)) {
    echo "Step 3: Matches +81[789]0 pattern\n";
    // +81の後の0を削除
    $phone = '+81' . substr($phone, 4);
} else {
    echo "Step 3: Does not match +81[789]0 pattern\n";
}

// Step 4: 080, 090, 070などで始まる場合
if (preg_match('/^0[789]0/', $phone)) {
    echo "Step 4: Matches 0[789]0 pattern (080, 090, 070)\n";
    // 最初の0を削除して+81を追加
    $phone = '+81' . substr($phone, 1);
} else {
    echo "Step 4: Does not match 0[789]0 pattern\n";
}

echo "Final result: $phone\n";

// 正しい結果
$correctPhone = '+81803372305';
echo "Correct result should be: $correctPhone\n";
echo "Match: " . ($phone === $correctPhone ? 'YES' : 'NO') . "\n";