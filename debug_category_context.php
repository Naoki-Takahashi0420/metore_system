<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ReservationContextService;

$contextService = new ReservationContextService();

// カテゴリ選択ページのコンテキスト
$encodedContext = "eyJpdiI6IlVLck9mNC8vZHkxakIyc09aN0tyVFE9PSIsInZhbHVlIjoiNThuNGQwMGFtY2NTdDJ5WnF0UTU3Y3JaY2FQdWo5K2FSUFpYY2FjaEFQdUNqWDdMUXd1MDRSb2JtM3NvZTVoM2dGaHdLTis3SkVLNy9LRGx5bEVxOW84enlMVldpeGVZZ3Z6Vklmc21VWEpDaDhVRzJPYlAvNVlqZ1lEbGlUbW4vWTYxTXRWdmdZZndEUHp5NEJwTlF4MFlXWXVZNzMzT2J5NW5JOHFOcnFWTWxvbWQxSURyL1N4Zi9odzBUeTJDOEIvMzA2SzhPRTVpQUY3UExCZGdvdz09IiwibWFjIjoiZjZiMjkyNDU1Yzc2Y2I5ZWNhMzk1YWM5MzlhMmU1YTFhZmMxYWM3MTg5MTdmNzZlOTFmNGZkY2JjMGFmZGVkMCIsInRhZyI6IiJ9";

echo "カテゴリ選択ページのコンテキスト:\n";

try {
    $context = $contextService->decryptContext($encodedContext);

    echo "復号化成功:\n";
    print_r($context);

    echo "\n重要項目:\n";
    echo "customer_id: " . ($context['customer_id'] ?? 'なし') . "\n";
    echo "is_existing_customer: " . ($context['is_existing_customer'] ?? 'なし') . "\n";
    echo "type: " . ($context['type'] ?? 'なし') . "\n";
    echo "source: " . ($context['source'] ?? 'なし') . "\n";

} catch (Exception $e) {
    echo "復号化エラー: " . $e->getMessage() . "\n";
}