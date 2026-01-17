<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Sale;
use App\Models\CustomerSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixBatchBillingSales extends Command
{
    protected $signature = 'sales:fix-batch-billing {--dry-run : 実際には変更しない}';
    protected $description = '2026-01-17に誤って計上されたバッチ売上を正しい日付に修正';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('バッチ売上修正' . ($dryRun ? ' (DRY RUN)' : ''));

        // 誤って計上された売上を取得
        $sales = Sale::where('notes', 'LIKE', '%自動計上（バッチ）%')
            ->whereDate('sale_date', '2026-01-17')
            ->with(['customerSubscription', 'customer'])
            ->get();

        $this->info("対象売上: {$sales->count()}件");

        if ($sales->isEmpty()) {
            $this->info('修正対象の売上はありません');
            return Command::SUCCESS;
        }

        $successCount = 0;
        $errorCount = 0;

        DB::beginTransaction();

        try {
            foreach ($sales as $sale) {
                $subscription = $sale->customerSubscription;

                if (!$subscription) {
                    $this->error("  ❌ サブスク契約が見つからない: sale_id={$sale->id}");
                    $errorCount++;
                    continue;
                }

                // 正しい売上日を計算
                // next_billing_date は既に翌月に更新されているので、1ヶ月引く
                $currentNextBilling = Carbon::parse($subscription->next_billing_date);
                $correctSaleDate = $currentNextBilling->copy()->subMonthNoOverflow();

                // billing_start_date の日を使って正確な日付を計算
                $billingDay = Carbon::parse($subscription->billing_start_date)->day;
                $correctMonth = $correctSaleDate->month;
                $correctYear = $correctSaleDate->year;

                // 月末調整
                $lastDayOfMonth = Carbon::createFromDate($correctYear, $correctMonth, 1)->endOfMonth()->day;
                if ($billingDay > $lastDayOfMonth) {
                    $billingDay = $lastDayOfMonth;
                }

                $correctSaleDate = Carbon::createFromDate($correctYear, $correctMonth, $billingDay);

                $customerName = $sale->customer?->full_name ?? '不明';

                $this->line("  {$customerName}: {$sale->sale_date->format('Y-m-d')} → {$correctSaleDate->format('Y-m-d')}");

                if (!$dryRun) {
                    // 売上日を修正
                    $sale->update([
                        'sale_date' => $correctSaleDate,
                        'notes' => str_replace('自動計上（バッチ）', '自動計上（バッチ→日付修正済）', $sale->notes),
                    ]);
                }

                $successCount++;
            }

            if (!$dryRun) {
                DB::commit();
                Log::info('✅ バッチ売上の日付修正完了', ['count' => $successCount]);
            }

            $this->newLine();
            $this->info("完了: 成功={$successCount}, エラー={$errorCount}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("エラー: {$e->getMessage()}");
            Log::error('❌ バッチ売上修正失敗', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }
}
