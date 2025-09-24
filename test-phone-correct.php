<?php
echo "=== 日本の携帯電話番号E.164変換テスト ===\n\n";

$testNumbers = [
    '08033372305',  // 080-3337-2305
    '09012345678',  // 090-1234-5678
    '07011112222'   // 070-1111-2222
];

foreach ($testNumbers as $original) {
    echo "Original: $original\n";

    // 正しい変換（国内番号の最初の0を削除して+81を追加）
    $correct = '+81' . substr($original, 1);
    echo "Correct E.164: $correct\n";

    // 現在のコードの結果
    $phone = $original;
    if (preg_match('/^0[789]0/', $phone)) {
        $phone = '+81' . substr($phone, 1);
    }
    echo "Current code result: $phone\n";
    echo "Match: " . ($phone === $correct ? 'YES' : 'NO') . "\n";
    echo "---\n";
}

echo "\n=== 問題の原因調査 ===\n";
$testPhone = '08033372305';
echo "Test phone: $testPhone\n";
echo "substr(\$testPhone, 1): " . substr($testPhone, 1) . "\n";
echo "Expected: 8033372305\n";
echo "With +81: +81" . substr($testPhone, 1) . "\n";