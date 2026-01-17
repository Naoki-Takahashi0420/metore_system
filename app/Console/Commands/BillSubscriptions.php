<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CustomerSubscription;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BillSubscriptions extends Command
{
    protected $signature = 'subscription:bill {--date= : 請求日（デフォルトは今日）} {--dry-run : 実際には計上しない}';
    protected $description = 'サブスク契約の自動請求を実行';

    public function handle(): int
    {
        $billingDate = $this->option('date') ?? now()->format('Y-m-d');
        $dryRun = $this->option('dry-run');

        Log::info('🚀 サブスク自動請求開始', [
            'billing_date' => $billingDate,
            'dry_run' => $dryRun,
        ]);

        $this->info("サブスク自動請求: {$billingDate}" . ($dryRun ? ' (DRY RUN)' : ''));

        // その日に既に計上済みのサブスク契約IDを取得
        $postedSubscriptionIds = Sale::whereDate('sale_date', $billingDate)
            ->where('payment_source', 'subscription')
            ->whereNotNull('customer_subscription_id')
            ->where('total_amount', '>', 0)
            ->pluck('customer_subscription_id')
            ->toArray();

        // 請求対象のサブスク契約を取得
        // 1. 初回請求: billing_start_date <= 今日 かつ next_billing_date IS NULL（まだ請求されていない）
        // 2. 継続請求: next_billing_date <= 今日（過去分も含む）
        $subscriptions = CustomerSubscription::where(function ($query) use ($billingDate) {
                // 初回請求（まだ一度も請求されていない）
                $query->where(function ($q) use ($billingDate) {
                    $q->whereDate('billing_start_date', '<=', $billingDate)
                      ->whereNull('next_billing_date');
                })
                // または継続請求
                ->orWhereDate('next_billing_date', '<=', $billingDate);
            })
            ->where('status', 'active')
            ->whereNotIn('id', $postedSubscriptionIds)
            ->with(['customer', 'store', 'menu'])
            ->orderBy('store_id')
            ->orderBy('next_billing_date')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('請求対象のサブスク契約はありません');
            Log::info('✅ 請求対象なし');
            return Command::SUCCESS;
        }

        $this->info("請求対象: {$subscriptions->count()}件");

        $successCount = 0;
        $skipCount = 0;
        $errorCount = 0;

        foreach ($subscriptions as $subscription) {
            // 決済失敗フラグが立っている場合はスキップ
            if ($subscription->payment_failed) {
                Log::info('⏭️ 決済失敗のためスキップ', [
                    'subscription_id' => $subscription->id,
                    'customer' => $subscription->customer?->full_name,
                ]);
                $this->warn("  スキップ（決済失敗）: {$subscription->customer?->full_name}");
                $skipCount++;
                continue;
            }

            // 初回請求日がまだ来ていない場合はスキップ
            if ($subscription->billing_start_date && Carbon::parse($subscription->billing_start_date)->gt(Carbon::parse($billingDate))) {
                Log::info('⏭️ 初回請求日未到来のためスキップ', [
                    'subscription_id' => $subscription->id,
                    'billing_start_date' => $subscription->billing_start_date,
                ]);
                $skipCount++;
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY RUN] {$subscription->customer?->full_name} - ¥" . number_format($subscription->monthly_price ?? 0));
                $successCount++;
                continue;
            }

            try {
                $paymentMethod = $subscription->payment_method ?? '現金';

                // 売上計上
                Sale::create([
                    'sale_number' => Sale::generateSaleNumber(),
                    'customer_id' => $subscription->customer_id,
                    'customer_subscription_id' => $subscription->id,
                    'store_id' => $subscription->store_id,
                    'sale_date' => $billingDate,
                    'sale_time' => now()->format('H:i:s'),
                    'payment_source' => 'subscription',
                    'payment_method' => $paymentMethod,
                    'total_amount' => $subscription->monthly_price ?? 0,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                    'status' => 'completed',
                    'notes' => 'サブスク決済（' . $subscription->plan_name . '）- 自動計上（バッチ）',
                    'handled_by' => 'システム',
                    'staff_id' => null,
                ]);

                // 次回請求日を更新
                $this->updateNextBillingDate($subscription, $billingDate);

                Log::info('✅ サブスク決済を自動計上', [
                    'subscription_id' => $subscription->id,
                    'customer' => $subscription->customer?->full_name,
                    'store' => $subscription->store?->name,
                    'amount' => $subscription->monthly_price,
                ]);

                $this->info("  ✅ {$subscription->customer?->full_name} ({$subscription->store?->name}) - ¥" . number_format($subscription->monthly_price ?? 0));
                $successCount++;

            } catch (\Exception $e) {
                Log::error('❌ サブスク決済の自動計上失敗', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("  ❌ {$subscription->customer?->full_name}: {$e->getMessage()}");
                $errorCount++;
            }
        }

        $summary = "完了: 成功={$successCount}, スキップ={$skipCount}, エラー={$errorCount}";
        $this->newLine();
        $this->info($summary);

        Log::info("🎉 サブスク自動請求完了", [
            'success' => $successCount,
            'skip' => $skipCount,
            'error' => $errorCount,
        ]);

        return $errorCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * 次回請求日を翌月に更新
     */
    private function updateNextBillingDate(CustomerSubscription $subscription, string $billingDate): void
    {
        $billingStartDate = $subscription->billing_start_date;
        if (!$billingStartDate) {
            return;
        }

        $originalDay = Carbon::parse($billingStartDate)->day;
        $currentDate = Carbon::parse($billingDate);
        $nextMonth = $currentDate->copy()->addMonthNoOverflow();
        $lastDayOfNextMonth = $nextMonth->daysInMonth;

        if ($originalDay > $lastDayOfNextMonth) {
            $nextBillingDate = $nextMonth->endOfMonth();
        } else {
            $nextBillingDate = $nextMonth->startOfMonth()->day($originalDay);
        }

        $subscription->update(['next_billing_date' => $nextBillingDate]);

        Log::info('📅 次回請求日を更新', [
            'subscription_id' => $subscription->id,
            'next_billing_date' => $nextBillingDate->format('Y-m-d'),
        ]);
    }
}
