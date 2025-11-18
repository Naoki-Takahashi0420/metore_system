<?php

namespace App\Console\Commands;

use App\Models\FcInvoice;
use App\Services\FcNotificationService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class FcPaymentReminder extends Command
{
    protected $signature = 'fc:payment-reminder {--days=3 : 支払期限までの日数}';
    protected $description = 'FC請求書の支払期限リマインダーを送信';

    public function handle()
    {
        $days = $this->option('days');
        $targetDate = Carbon::today()->addDays($days);

        $invoices = FcInvoice::unpaid()
            ->whereDate('due_date', $targetDate)
            ->get();

        $this->info("支払期限が{$days}日後の請求書: {$invoices->count()}件");

        $notificationService = app(FcNotificationService::class);
        $sent = 0;

        foreach ($invoices as $invoice) {
            try {
                $notificationService->notifyPaymentReminder($invoice);
                $sent++;
                $this->line("✓ {$invoice->invoice_number} ({$invoice->fcStore->name})");
            } catch (\Exception $e) {
                $this->error("✗ {$invoice->invoice_number}: {$e->getMessage()}");
            }
        }

        $this->info("リマインダー送信完了: {$sent}/{$invoices->count()}件");

        return 0;
    }
}
