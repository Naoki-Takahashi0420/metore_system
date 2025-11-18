<?php

namespace App\Console\Commands;

use App\Models\FcInvoice;
use App\Services\FcNotificationService;
use Illuminate\Console\Command;

class FcCheckOverdue extends Command
{
    protected $signature = 'fc:check-overdue';
    protected $description = 'FC請求書の支払期限超過をチェックし通知';

    public function handle()
    {
        // 支払期限超過の請求書を自動マーク
        $markedCount = FcInvoice::markOverdueInvoices();
        $this->info("支払期限超過としてマークした請求書: {$markedCount}件");

        // 新たに超過となった請求書を通知
        $overdueInvoices = FcInvoice::overdue()->get();
        $this->info("支払期限超過の請求書（総数）: {$overdueInvoices->count()}件");

        $notificationService = app(FcNotificationService::class);
        $notified = 0;

        foreach ($overdueInvoices as $invoice) {
            // 超過から7日以内のものだけ通知（毎日通知を避けるため）
            $overdueDays = now()->diffInDays($invoice->due_date);
            if ($overdueDays <= 7) {
                try {
                    $notificationService->notifyPaymentOverdue($invoice);
                    $notified++;
                    $this->line("⚠ {$invoice->invoice_number} ({$invoice->fcStore->name}) - {$overdueDays}日超過");
                } catch (\Exception $e) {
                    $this->error("✗ {$invoice->invoice_number}: {$e->getMessage()}");
                }
            }
        }

        $this->info("超過アラート送信: {$notified}件");

        return 0;
    }
}
