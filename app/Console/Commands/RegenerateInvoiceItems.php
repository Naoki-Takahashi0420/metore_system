<?php

namespace App\Console\Commands;

use App\Models\FcInvoice;
use Illuminate\Console\Command;

class RegenerateInvoiceItems extends Command
{
    protected $signature = 'fc:regenerate-invoice-items';
    protected $description = '請求書アイテムを発注データから再生成します';

    public function handle()
    {
        $this->info('=== 請求書アイテム再生成 ===');

        $invoices = FcInvoice::whereIn('status', ['issued', 'sent', 'paid'])->get();

        foreach ($invoices as $invoice) {
            $beforeCount = $invoice->items()->count();
            $this->line("{$invoice->invoice_number}: items={$beforeCount}");

            if ($beforeCount == 0) {
                $result = $invoice->regenerateItemsFromOrders();
                $invoice->refresh();
                $afterCount = $invoice->items()->count();
                $status = $result ? 'regenerated' : 'no orders found';
                $this->info("  -> {$afterCount} ({$status})");
            } else {
                $this->line("  (already has items)");
            }
        }

        $this->info('=== 完了 ===');
        return 0;
    }
}
