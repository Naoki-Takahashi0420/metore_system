<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ReservationContextService;

$contextService = new ReservationContextService();

// カテゴリ選択ページのコンテキスト
$encodedContext = "eyJpdiI6Im4xUFNlZmlpdzR0bnpQRGtSZE1vMEE9PSIsInZhbHVlIjoiaUVOZjRkUXNCT2hJZVJPYmFIY2NYOVk2UVVZcklCZlhkd0F3dGZLOXdKOEhmL1BWQkIyY0pWNnQvcGpGNzJtbXhyeEpKTGdsd3FGM0xXejJ4U2VSM0M5dUplZVZpOC9ZZ283ZFlhMVJLcGxKR0JnM045OEhHYldGVDRnUU12WklZSlY0NTJWM3VuSXVYVk9wWDJ0TEo4SnpNR3E5MlhtSXNJZE9sT3V6ck9CV01MWThvSGJZZ2V1a09JZnI5blg5WjlsQjYyc1lzWFpRcDNCUXFzWjgrUT09IiwibWFjIjoiMGY0OTlhYjkwZWM4NWU4NTRhNWY2NGZiMWFkZWIwMDAyMWNiZDBmYWQ3ZWIyYmQzZTdhZDQxZTAyZWI1YWQ2ZiIsInRhZyI6IiJ9";

echo "カテゴリ選択ページのコンテキスト:\n";

try {
    $context = $contextService->decryptContext($encodedContext);

    echo "\n復号化されたコンテキスト:\n";
    print_r($context);

    echo "\n=== 重要な判定項目 ===\n";
    echo "customer_id: " . ($context['customer_id'] ?? 'なし') . "\n";
    echo "is_existing_customer: " . ($context['is_existing_customer'] ?? 'なし') . "\n";
    echo "type: " . ($context['type'] ?? 'なし') . "\n";
    echo "source: " . ($context['source'] ?? 'なし') . "\n";
    echo "store_id: " . ($context['store_id'] ?? 'なし') . "\n";

} catch (Exception $e) {
    echo "復号化エラー: " . $e->getMessage() . "\n";
}