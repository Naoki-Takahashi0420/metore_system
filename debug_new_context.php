<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ReservationContextService;

$contextService = new ReservationContextService();

// 新しいコンテキスト
$encodedContext = "eyJpdiI6IkprUFM0ZjlxVEwwMzJwNzBnazNqRXc9PSIsInZhbHVlIjoiOWRaU3pPcHlRWnNlM3FkbGVlU2UyVUt2V2U5Q3o5dHFQemJyYzVxdjhIUU55cnhvRGhYMW1LM3dCeVpvR1Rsb0l5RGxPQm9GaWU1aENndXhQV2lydmUrS1EyVzVudGdKd2JlaTMvODNlUmRiSnNaWTgvdXZKMnVOMFBwRXNuL1BCbXgvY0pxNThhZ1dEOUoyeUpmajZMeXVwZHJDR0JYSnZ0NGRrUWZRclloVDNMcFJmay9DV0xFaURFc2dQQVpkWDFwYit1RWxxcFhFdUZ4U25HeTM2RFY5MkZnTHYrRENYQW9tdUhtWDh4VERXZTlhTXdTbnovZUxIOW1KVEVNeSIsIm1hYyI6ImUzOTIwYmIyNWMwY2ZkNTBkNGE3MDg5MTE5MmIyNmEwMjNiYTY4YTc0NDIzMWU0MzIyNWI0M2RkZWZiMzg1YjciLCJ0YWciOiIifQ%3D%3D";

$decodedContext = urldecode($encodedContext);

echo "最新のカレンダーページコンテキスト:\n";
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