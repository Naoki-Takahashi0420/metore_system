<?php
// 本番環境テーブルチェックスクリプト

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $pdo = new PDO(
        "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_DATABASE'],
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD']
    );

    echo "データベース接続成功\n";

    // テーブルの存在確認
    $tables = ['reservation_menu_options', 'menu_options'];

    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            echo "✅ テーブル '{$table}' は存在します\n";

            // テーブル構造も確認
            $describe = $pdo->query("DESCRIBE {$table}");
            echo "   カラム: ";
            while ($row = $describe->fetch(PDO::FETCH_ASSOC)) {
                echo $row['Field'] . " ";
            }
            echo "\n";
        } else {
            echo "❌ テーブル '{$table}' は存在しません\n";
        }
    }

    // マイグレーション状況確認
    $stmt = $pdo->query("SELECT migration FROM migrations WHERE migration LIKE '%menu_option%'");
    echo "\n実行済みマイグレーション:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "✅ " . $row['migration'] . "\n";
    }

} catch (PDOException $e) {
    echo "エラー: " . $e->getMessage() . "\n";
}