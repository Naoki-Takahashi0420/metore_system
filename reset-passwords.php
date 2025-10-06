<?php
/**
 * ユーザーパスワードをリセットするスクリプト
 *
 * 全ユーザーのパスワードを "password" にリセットします
 *
 * 実行方法: php reset-passwords.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "=== ユーザーパスワードリセットスクリプト ===" . PHP_EOL;
echo PHP_EOL;

$users = User::all();

echo "対象ユーザー数: " . $users->count() . PHP_EOL;
echo PHP_EOL;

echo "全ユーザーのパスワードを 'password' にリセットしますか？ (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));

if (strtolower($line) !== 'yes') {
    echo "リセットをキャンセルしました。" . PHP_EOL;
    exit(0);
}

echo PHP_EOL;
echo "=== リセット実行中 ===" . PHP_EOL;
echo PHP_EOL;

$updatedCount = 0;

foreach ($users as $user) {
    $user->password = 'password';
    $user->save();

    echo "✅ ID: {$user->id}, Name: {$user->name}, Email: {$user->email}" . PHP_EOL;
    $updatedCount++;
}

echo PHP_EOL;
echo "=== リセット完了 ===" . PHP_EOL;
echo "リセット件数: {$updatedCount}" . PHP_EOL;
echo "新しいパスワード: password" . PHP_EOL;
echo PHP_EOL;
