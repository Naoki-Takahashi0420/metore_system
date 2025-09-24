<?php
echo "=== 正規表現デバッグ ===\n\n";

$phone = '08033372305';
echo "Test phone: $phone\n";

// パターン1: ^0([789]0\d{8})$
$pattern1 = '/^0([789]0\d{8})$/';
if (preg_match($pattern1, $phone, $matches)) {
    echo "Pattern 1 matches!\n";
    echo "Full match: {$matches[0]}\n";
    echo "Group 1: {$matches[1]}\n";
    echo "Result would be: +81{$matches[1]}\n";
} else {
    echo "Pattern 1 does not match\n";
}

// 文字数チェック
echo "Phone length: " . strlen($phone) . "\n";

// 手動分解
echo "First char: " . $phone[0] . "\n";
echo "Second char: " . $phone[1] . "\n";
echo "Third char: " . $phone[2] . "\n";
echo "From 1: " . substr($phone, 1) . "\n";

// より詳細な正規表現テスト
$patterns = [
    '/^0([789]0\d{8})$/' => '080,090,070 + 8桁',
    '/^0([789]0\d{7,8})$/' => '080,090,070 + 7-8桁',
    '/^0[789]0/' => '080,090,070で始まる',
    '/^08033372305$/' => '完全一致'
];

foreach ($patterns as $pattern => $desc) {
    $matches = [];
    if (preg_match($pattern, $phone, $matches)) {
        echo "$desc: MATCH\n";
        if (count($matches) > 1) {
            echo "  Group 1: {$matches[1]}\n";
        }
    } else {
        echo "$desc: NO MATCH\n";
    }
}

// 正しい桁数での確認
echo "\nDigit analysis:\n";
echo "After 0: " . substr($phone, 1) . " (length: " . strlen(substr($phone, 1)) . ")\n";
echo "Should be 10 digits total\n";