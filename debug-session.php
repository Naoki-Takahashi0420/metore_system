<?php
session_start();

echo "<h2>現在のセッション内容:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>セッションID:</h2>";
echo session_id();

echo "<h2>Laravel セッション（別管理の可能性）:</h2>";
echo "<p>※Laravelは独自のセッション管理を使用しています。</p>";

// Laravelセッションファイルのパスを表示
$sessionPath = __DIR__ . '/storage/framework/sessions/';
if (is_dir($sessionPath)) {
    echo "<p>セッションファイルディレクトリ: " . $sessionPath . "</p>";
    $files = scandir($sessionPath);
    echo "<p>セッションファイル数: " . (count($files) - 2) . "</p>";
}
?>