<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ReservationContextService;

$contextService = new ReservationContextService();

// URLエンコードされたcontextパラメータ
$encodedContext = "eyJpdiI6IlVUcmZwREN3WTd0V3dNOG5JUHh6RkE9PSIsInZhbHVlIjoiLzN1OVdVZ1FlcHphTEtZTEFkVDF0MXduWXd3a0x0ZHNQd1ZIU0d4eWE5RnFmU1Nwck1yb3lXcmxaYUFQQy85c1FOWkZTMjdrNkNKeVBuYTJ6Sml0MGZvc09VcjJlbkpvVzg4S1plaHBleVR6R0d0N1crQVJySUJJaFQ3VWpqcGYvc1l6aUxqYlFMcEhMNFFidWZPZy9PMEN5YWowUlViSlRrclR6RUJwWGFCbWxIWnBtTFE1dldnVnFmTEhNWE9sUGd1NzNKUTlPcnVyU0pNUWRjeHdYWlBGSVRYaHVENG1renZBalFuWW5UOD0iLCJtYWMiOiI3ZDhmZjIzNDlkZmEwZGI5NTI2YTc2ODZhN2ZkZTU2YWM0ZTBiODMzZTJmMDM5NzczYzBmMTdiYzIxNjUwODJkIiwidGFnIjoiIn0%3D";

// URLデコード
$decodedContext = urldecode($encodedContext);

echo "URLデコード後: " . $decodedContext . "\n\n";

try {
    // コンテキストを復号化
    $context = $contextService->decryptContext($decodedContext);

    echo "復号化されたコンテキスト:\n";
    print_r($context);

    echo "\n顧客タイプ判定:\n";
    echo "is_existing_customer: " . ($context['is_existing_customer'] ?? 'なし') . "\n";
    echo "customer_id: " . ($context['customer_id'] ?? 'なし') . "\n";
    echo "type: " . ($context['type'] ?? 'なし') . "\n";
    echo "source: " . ($context['source'] ?? 'なし') . "\n";

} catch (Exception $e) {
    echo "復号化エラー: " . $e->getMessage() . "\n";
}