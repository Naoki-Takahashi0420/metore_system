<?php
echo "=== 電話番号構造検証 ===\n\n";

$phone = '08033372305';
echo "Phone: $phone\n";
echo "Length: " . strlen($phone) . "\n";

// 正しい分解方法
echo "\nCorrect breakdown:\n";
echo "Prefix: " . substr($phone, 0, 3) . " (080)\n";
echo "Number: " . substr($phone, 3) . " (33372305)\n";
echo "Number length: " . strlen(substr($phone, 3)) . "\n";

// E.164変換
$prefix = substr($phone, 1, 2); // 80 (0を除いた2桁)
$number = substr($phone, 3);    // 33372305 (残りの8桁)
$e164 = '+81' . $prefix . $number;

echo "\nE.164 conversion:\n";
echo "Remove first 0: " . substr($phone, 1) . "\n";
echo "Prefix (2 digits): $prefix\n";
echo "Number (8 digits): $number\n";
echo "E.164 result: $e164\n";

// 検証
echo "\nVerification:\n";
echo "Expected: +81803372305\n";
echo "Actual:   $e164\n";
echo "Match: " . ($e164 === '+81803372305' ? 'YES' : 'NO') . "\n";