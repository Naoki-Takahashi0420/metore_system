<?php
// OPcache完全クリア
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache cleared\n";
} else {
    echo "❌ OPcache not available\n";
}

// Filament キャッシュクリア
require_once __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->call('optimize:clear');
$kernel->call('cache:clear');
$kernel->call('view:clear');
$kernel->call('config:clear');
$kernel->call('route:clear');

echo "✅ All Laravel caches cleared\n";
