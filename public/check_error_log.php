<?php
// エラーログの最新部分を表示
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$logFile = storage_path('logs/laravel.log');

if (!file_exists($logFile)) {
    die('Log file not found');
}

// 最後の500行を取得
$lines = array_slice(file($logFile), -500);

header('Content-Type: text/plain; charset=utf-8');

// 500エラーまたは重要なエラーを探す
$inError = false;
$errorBuffer = [];
$errorCount = 0;

foreach ($lines as $line) {
    // エラーの開始を検出
    if (strpos($line, 'ERROR') !== false || strpos($line, 'CRITICAL') !== false || strpos($line, '500 Internal') !== false) {
        $inError = true;
        $errorCount++;
        $errorBuffer[] = "\n=== Error #$errorCount ===\n";
    }

    if ($inError) {
        $errorBuffer[] = $line;

        // スタックトレースの終わりを検出
        if (strpos($line, '"}') !== false || strpos($line, '#0') === false && strlen(trim($line)) == 0) {
            $inError = false;
        }
    }

    // コンテキスト関連のログも表示
    if (strpos($line, 'コンテキスト取得結果') !== false ||
        strpos($line, 'モーダル表示判定') !== false ||
        strpos($line, 'マイページからの予約') !== false ||
        strpos($line, '予約ソースの判定') !== false) {
        $errorBuffer[] = $line;
    }
}

// 最後の50エントリのみ表示
$errorBuffer = array_slice($errorBuffer, -200);

echo "=== Laravel Error Log (Last Errors) ===\n\n";
echo implode('', $errorBuffer);

if ($errorCount === 0) {
    echo "\nNo recent errors found.\n";
    echo "\n=== Last 50 lines ===\n";
    echo implode('', array_slice($lines, -50));
}