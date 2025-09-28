<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ReservationContextService;

$contextService = new ReservationContextService();

// 最新のカレンダーページのコンテキスト
$encodedContext = "eyJpdiI6IjhwUVpSUTNxdGdNYnJvY1JieVUwRkE9PSIsInZhbHVlIjoiaWR5SXQzRXExZDROdEc0S2NCcjZlTTF3cXNlVGFjYXhreGhabjBXNTdwQnB1V2xsbkpvRVQvZ09ZRTI2a0NuUjdVTGJickZKdTFXWFVZZ3FXbWRhNGtSdEtIK3R0VGwreWxhWkpuaDFQN2QrVkpMbzZ5ZmRIOElsOXJ6SHJFS1F6WVNRd2JXRHNkUlExN1pXKzE4T3o2ZWdYNTJUdFc1T24vc05QTzB1VTJlOFQwMm1JUHdaUjc2Y1hqZkNsVDBTSXJib0hjR0hEOFkrYi9zeC9qQUpEN25CZUp6TWdsQUMwWm1KeVlpTjN2Ly9qNTRBNTVLZ2drM1NNczVrUU5qdSIsIm1hYyI6ImYwZmEyMWViZDZjMDE0NzZjMzAwMjQyN2RiNDZiMWI0MjhkM2I1MWJkMDk4NDNlZTZkODIwNzk4NGNmY2Y4MGEiLCJ0YWciOiIifQ%3D%3D";

$decodedContext = urldecode($encodedContext);

echo "カレンダーページの最新コンテキスト:\n";

try {
    $context = $contextService->decryptContext($decodedContext);

    echo "\n復号化されたコンテキスト:\n";
    print_r($context);

    echo "\n=== 重要な判定項目 ===\n";
    echo "customer_id: " . ($context['customer_id'] ?? 'なし') . "\n";
    echo "is_existing_customer: " . ($context['is_existing_customer'] ?? 'なし') . "\n";
    echo "type: " . ($context['type'] ?? 'なし') . "\n";
    echo "source: " . ($context['source'] ?? 'なし') . "\n";
    echo "store_id: " . ($context['store_id'] ?? 'なし') . "\n";
    echo "category_id: " . ($context['category_id'] ?? 'なし') . "\n";

} catch (Exception $e) {
    echo "復号化エラー: " . $e->getMessage() . "\n";
}