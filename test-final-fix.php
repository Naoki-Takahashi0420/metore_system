<?php
echo "=== 最終修正版テスト ===\n\n";

$phone = '08033372305';
echo "Test phone: $phone\n";

// 新パターン: ^0([789]0)(\d{8})$
$pattern = '/^0([789]0)(\d{8})$/';
if (preg_match($pattern, $phone, $matches)) {
    echo "Pattern matches!\n";
    echo "Full match: {$matches[0]}\n";
    echo "Group 1 ([789]0): {$matches[1]}\n";
    echo "Group 2 (8 digits): {$matches[2]}\n";
    $result = '+81' . $matches[1] . $matches[2];
    echo "Result: $result\n";
    echo "Expected: +81803372305\n";
    echo "Correct: " . ($result === '+81803372305' ? 'YES' : 'NO') . "\n";
} else {
    echo "Pattern does not match\n";
}

// 全テストケース
$testCases = [
    '08033372305' => '+81803372305',
    '09012345678' => '+81901234678',
    '07011112222' => '+81701111222'
];

echo "\n=== 全テストケース ===\n";
foreach ($testCases as $input => $expected) {
    if (preg_match('/^0([789]0)(\d{8})$/', $input, $matches)) {
        $result = '+81' . $matches[1] . $matches[2];
        echo "$input → $result (expected: $expected) " . ($result === $expected ? '✓' : '✗') . "\n";
    }
}