<?php
require_once 'vendor/autoload.php';

use App\Services\SmsService;

// Laravelアプリケーションのブートストラップ
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== 実際のSmsService動作トレース ===\n\n";

$smsService = new SmsService();
$reflection = new ReflectionClass($smsService);
$formatMethod = $reflection->getMethod('formatPhoneNumber');
$formatMethod->setAccessible(true);

$testPhone = '08033372305';
echo "Input: $testPhone\n";

// ステップごとにトレース
class TracingSmsService extends SmsService {
    public function traceFormatPhoneNumber(string $phone): string {
        echo "=== formatPhoneNumber トレース ===\n";
        echo "Input: '$phone'\n";

        // ハイフン、スペース、その他の記号を除去
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        echo "After cleanup: '$phone'\n";

        // すでに+81で始まる場合はそのまま
        if (strpos($phone, '+81') === 0) {
            echo "Already starts with +81, returning: '$phone'\n";
            return $phone;
        }

        // +8180, +8190などの場合（080, 090の0が残っている）
        if (preg_match('/^\+81[789]0/', $phone)) {
            echo "Matches +81[789]0 pattern\n";
            // +81の後の0を削除
            $phone = '+81' . substr($phone, 4);
            echo "After removing extra 0: '$phone'\n";
            return $phone;
        }

        // 080, 090, 070などで始まる場合
        if (preg_match('/^0[789]0/', $phone)) {
            echo "Matches 0[789]0 pattern\n";
            // 最初の0を削除して+81を追加
            $phone = '+81' . substr($phone, 1);
            echo "After adding +81: '$phone'\n";
            return $phone;
        }

        // その他の0から始まる番号
        if (strpos($phone, '0') === 0) {
            echo "Starts with 0\n";
            $phone = '+81' . substr($phone, 1);
            echo "After adding +81: '$phone'\n";
            return $phone;
        }

        // 8180, 8190などで始まる場合（+が無い）
        if (preg_match('/^81[789]0/', $phone)) {
            echo "Matches 81[789]0 pattern\n";
            // 810を削除して+81を追加
            $phone = '+81' . substr($phone, 3);
            echo "After fixing: '$phone'\n";
            return $phone;
        }

        // 81で始まる場合
        if (strpos($phone, '81') === 0) {
            echo "Starts with 81\n";
            $phone = '+' . $phone;
            echo "After adding +: '$phone'\n";
            return $phone;
        }

        // それ以外（80, 90などで始まる場合）
        if (preg_match('/^[789]0/', $phone)) {
            echo "Matches [789]0 pattern\n";
            $phone = '+81' . $phone;
            echo "After adding +81: '$phone'\n";
            return $phone;
        }

        // +がない場合は追加
        if (strpos($phone, '+') !== 0) {
            echo "No + prefix\n";
            $phone = '+' . $phone;
            echo "After adding +: '$phone'\n";
        }

        return $phone;
    }
}

$tracingService = new TracingSmsService();
$result = $tracingService->traceFormatPhoneNumber($testPhone);
echo "\nFinal result: $result\n";