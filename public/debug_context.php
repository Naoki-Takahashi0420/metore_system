<?php
// 本番環境のAPP_KEYを使用するために、Laravelのブートストラップを読み込む
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ReservationContextService;

$ctx = $_GET['ctx'] ?? '';

if (!$ctx) {
    die('ctx parameter is missing');
}

$contextService = new ReservationContextService();

try {
    $context = $contextService->decryptContext($ctx);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'context' => $context,
        'source' => $context['source'] ?? 'not_set',
        'is_existing_customer' => $context['is_existing_customer'] ?? 'not_set',
        'customer_id' => $context['customer_id'] ?? 'not_set'
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}