<?php
require_once 'vendor/autoload.php';

use App\Services\SmsService;

// Laravelアプリケーションのブートストラップ
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== 修正版フォーマット機能テスト ===\n\n";

$smsService = new SmsService();
$reflection = new ReflectionClass($smsService);
$formatMethod = $reflection->getMethod('formatPhoneNumber');
$formatMethod->setAccessible(true);

$testNumbers = [
    '08033372305',  // 080-3337-2305
    '09012345678',  // 090-1234-5678
    '07011112222',  // 070-1111-2222
    '050-1234-5678', // 050番号（ハイフンあり）
    '+81803372305', // 既に正しい形式
    '+818033372305', // 間違った形式
];

foreach ($testNumbers as $testPhone) {
    $result = $formatMethod->invoke($smsService, $testPhone);
    echo "Input:  $testPhone\n";
    echo "Output: $result\n";

    // 期待値を計算
    $clean = preg_replace('/[^0-9+]/', '', $testPhone);
    if (preg_match('/^0([789]0\d{8})$/', $clean, $matches)) {
        $expected = '+81' . $matches[1];
    } else if (strpos($clean, '+81') === 0) {
        $expected = $clean;
    } else {
        $expected = '(需要手動判断)';
    }

    echo "Expected: $expected\n";
    echo "Correct: " . ($result === $expected ? 'YES' : 'NO') . "\n";
    echo "---\n";
}

// 特に08033372305をテスト
echo "\n=== 特別テスト: 08033372305 ===\n";
$targetPhone = '08033372305';
$result = $formatMethod->invoke($smsService, $targetPhone);
echo "Input: $targetPhone\n";
echo "Output: $result\n";
echo "Expected: +81803372305\n";
echo "Correct: " . ($result === '+81803372305' ? 'YES' : 'NO') . "\n";