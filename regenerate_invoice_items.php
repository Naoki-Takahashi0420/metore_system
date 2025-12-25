<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FcInvoice;

echo "=== 請求書アイテム再生成 ===" . PHP_EOL;

$invoices = FcInvoice::whereIn('status', ['issued', 'sent', 'paid'])->get();

foreach ($invoices as $invoice) {
    $beforeCount = $invoice->items()->count();
    echo "{$invoice->invoice_number}: items={$beforeCount}";

    if ($beforeCount == 0) {
        $result = $invoice->regenerateItemsFromOrders();
        $invoice->refresh();
        $afterCount = $invoice->items()->count();
        echo " -> {$afterCount}" . ($result ? " (regenerated)" : " (no orders found)");
    } else {
        echo " (already has items)";
    }
    echo PHP_EOL;
}

echo "=== 完了 ===" . PHP_EOL;
