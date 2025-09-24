<?php
echo "=== 新しい正規表現デバッグ ===\n\n";

$phone = '08033372305';
echo "Test phone: $phone\n";

// 新パターン: ^0([789])0(\d{8})$
$pattern = '/^0([789])0(\d{8})$/';
if (preg_match($pattern, $phone, $matches)) {
    echo "New pattern matches!\n";
    echo "Full match: {$matches[0]}\n";
    echo "Group 1 (7/8/9): {$matches[1]}\n";
    echo "Group 2 (8 digits): {$matches[2]}\n";
    echo "Result: +81{$matches[1]}0{$matches[2]}\n";
} else {
    echo "New pattern does not match\n";
}

// 確認のための手動分解
echo "\nManual breakdown:\n";
echo "First digit: {$phone[0]}\n";
echo "Second digit: {$phone[1]}\n"; // 8
echo "Third digit: {$phone[2]}\n";  // 0
echo "Remaining: " . substr($phone, 3) . "\n"; // 33372305

echo "\nExpected result: +81803372305\n";

// 他の番号でもテスト
$numbers = ['08033372305', '09012345678', '07011112222'];
foreach ($numbers as $num) {
    if (preg_match('/^0([789])0(\d{8})$/', $num, $matches)) {
        $result = '+81' . $matches[1] . '0' . $matches[2];
        echo "$num → $result\n";
    }
}