<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ReservationContextService;

$contextService = new ReservationContextService();

// 最終的なカレンダーページのコンテキスト
$encodedContext = "eyJpdiI6IjZRaENoSVNhcWJXbGg3OWRIYXpBclE9PSIsInZhbHVlIjoiY2cwbHMwNVRZUWJVNlpOMk9yWndNUGhqOEZ6bHpsb2lhV2xPL1EvdStlckRZTWkzUkw2SjA1YmQwRHRUbmpXUDArOWhTMHpHakFqaENDMDQrZ2ljc3FGZEpCWUEvNGRjQnFDUzNMUVVOdXNoVG1sWjI4bVFUNXZzcldUeFJPeUlZMzE0T0ZCbkdlQ0hQa09GUlMxS3kvemFjNXN4ZDNvWTc4ekxkcmxyc29JKzhSNnU1WHJaZHdON2JoUnRLMVB6N2g2bElKVnRKZGRqM1AwQnNIdGxZZEUxNWJrOHJFeUc1Uy81OHRrcGNUUUFNcDBGMnhMWFIvbmhEc09WSmRUQiIsIm1hYyI6IjgxOGE1NTQ0ODNiNTRiOTRmZjg1YWJjMTFkNzFkZDM1NzkzM2NkNzFhOWM0MTI4NTc4NmZkMzAzNjdkOTMxMjEiLCJ0YWciOiIifQ%3D%3D";

$decodedContext = urldecode($encodedContext);

echo "最終カレンダーページのコンテキスト:\n";
echo "URLデコード後: " . $decodedContext . "\n\n";

try {
    $context = $contextService->decryptContext($decodedContext);

    echo "復号化されたコンテキスト:\n";
    print_r($context);

    echo "\n重要な判定項目:\n";
    echo "customer_id: " . ($context['customer_id'] ?? 'なし') . "\n";
    echo "is_existing_customer: " . ($context['is_existing_customer'] ?? 'なし') . "\n";
    echo "type: " . ($context['type'] ?? 'なし') . "\n";
    echo "source: " . ($context['source'] ?? 'なし') . "\n";

} catch (Exception $e) {
    echo "復号化エラー: " . $e->getMessage() . "\n";
}