<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ReservationContextService;

$contextService = new ReservationContextService();

// 時間選択ページのコンテキスト
$encodedContext = "eyJpdiI6Ik4vaWM5NG9GTHZ2U0RmT0ZzdFpMNFE9PSIsInZhbHVlIjoiSVVNemR0N0MxQ09MM0RnWkd2VThMYVRBVkcvcTJLR3BkYmkxb2lDZXVNT0ZPd21SSlh0RVErblpCMVd2MHRiUjVod25zZWUvRmVyWktQSy9OUVl4V0Vhc1RCbVlodDlQeXZ5RXZhQk91Y0t3cnowcGZWdEpwZW96R3YrMTRZaU5pV3VCY0hVVFJRc0xSc2NpamQ3YW9SQUNWeUl5aWR5dEMzaCtZbnhWU2Y2dkpLWWo0d1MvVS96VkVMbVJNeTdDK2ZmZkt0K1M1emVlTFBOL3o3T1NHbTF6SVNYVDdTaXdibXc5YTNZaCtKOFgwQjVQRnVHZlE4RVYxRHNMZnVuaCIsIm1hYyI6IjFjMjkzMjA4ODJkY2ViYjFkNWQ4YTMzM2JlYjAwNTEwNjk4NzJmYTA5MmMxYmQxZDIxZTVkMGZjMWY0MDViZTQiLCJ0YWciOiIifQ%3D%3D";

$decodedContext = urldecode($encodedContext);

echo "時間選択ページのコンテキスト:\n";

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