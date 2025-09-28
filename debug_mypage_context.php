<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ReservationContextService;

$contextService = new ReservationContextService();

// マイページからのURLエンコードされたcontextパラメータ
$encodedContext = "eyJpdiI6IjZCb05lSVB3Y3FFWU5XUmhhRmNZY1E9PSIsInZhbHVlIjoiNnJUNzEzU2hweFhuODhlVncxclk0Vm5kM1F6dmczZitaeXJqaGxHNmIwMnBCeWE1OXBjZFJPZ0FLd3FNUUd4N2V0Y1JoN014cEYvZXVkMU9tWklKRUVCaVF0MjFUUU40cFdHK2RtWkRqOGlBL3FWTHUrSWN2VW9hRGwrc09CL1o5MmNORW9NQmQxaERaMjl3WmhKMEZFMFdoazJDQW9pOGp5QXZBc2d6TDBMOWJhalBGLzJ6WDNZNXVFSWMvZnlLMmM4OGVZMzROQnZkdTZ6cjZMZFRHTklKejJMdHpjODBjQmZQMUl6c1U3ZGIwcERtWEw4cXhVRUplcnFTckFVbiIsIm1hYyI6ImI4NGM2OWRlZjFmZTBhZTBlYTE1NWRlMTlmMjc0N2FkNDQwODQ0YWY5MzEwNDg0NmI5NDhjNDI5YjRmYmNhZTkiLCJ0YWciOiIifQ%3D%3D";

// URLデコード
$decodedContext = urldecode($encodedContext);

echo "URLデコード後: " . $decodedContext . "\n\n";

try {
    // コンテキストを復号化
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