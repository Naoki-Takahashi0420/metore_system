<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CustomerSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixSubscriptionBillingDates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:fix-billing-dates
                            {--force : 確認なしで実行}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'サブスク契約の次回請求日を一括更新';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 サブスク契約の次回請求日一括更新');
        $this->newLine();

        // 更新対象を取得
        $subscriptions = CustomerSubscription::with('customer')
            ->where('status', 'active')
            ->whereNotNull('billing_start_date')
            ->get();

        $updates = [];
        foreach ($subscriptions as $subscription) {
            $currentNextBilling = $subscription->next_billing_date
                ? Carbon::parse($subscription->next_billing_date)
                : null;

            $correctNextBilling = $this->calculateCorrectNextBillingDate($subscription);

            $needsUpdate = !$currentNextBilling ||
                          $currentNextBilling->format('Y-m-d') !== $correctNextBilling->format('Y-m-d');

            if ($needsUpdate) {
                $updates[] = [
                    'subscription' => $subscription,
                    'current' => $currentNextBilling,
                    'correct' => $correctNextBilling,
                ];
            }
        }

        if (empty($updates)) {
            $this->info('✅ 更新が必要な契約はありません！');
            return 0;
        }

        // サマリー表示
        $this->warn("⚠️  更新対象: " . count($updates) . "件");
        $this->newLine();

        // 確認プロンプト
        if (!$this->option('force')) {
            if (!$this->confirm('本当に更新しますか？', false)) {
                $this->comment('キャンセルしました。');
                return 1;
            }
        }

        // 更新実行
        $this->newLine();
        $this->info('🚀 更新開始...');
        $progressBar = $this->output->createProgressBar(count($updates));
        $progressBar->start();

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($updates as $item) {
                $subscription = $item['subscription'];
                $correctNextBilling = $item['correct'];

                try {
                    $subscription->update([
                        'next_billing_date' => $correctNextBilling,
                    ]);

                    Log::info('サブスク次回請求日修正', [
                        'subscription_id' => $subscription->id,
                        'customer_id' => $subscription->customer_id,
                        'old_next_billing_date' => $item['current'] ? $item['current']->format('Y-m-d') : null,
                        'new_next_billing_date' => $correctNextBilling->format('Y-m-d'),
                    ]);

                    $successCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ];
                }

                $progressBar->advance();
            }

            DB::commit();
            $progressBar->finish();
            $this->newLine(2);

            // 結果表示
            $this->info("✅ 更新完了");
            $this->table(
                ['項目', '件数'],
                [
                    ['成功', $successCount],
                    ['失敗', $errorCount],
                    ['合計', count($updates)],
                ]
            );

            if ($errorCount > 0) {
                $this->newLine();
                $this->error('❌ エラー詳細:');
                $this->table(
                    ['サブスクID', 'エラー'],
                    array_map(fn($e) => [$e['subscription_id'], $e['error']], $errors)
                );
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $progressBar->finish();
            $this->newLine(2);
            $this->error('❌ 更新中にエラーが発生しました: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * 正しい次回請求日を計算
     * 請求開始日から何ヶ月経過したかを計算し、今日以降で最も近い請求日を返す
     */
    private function calculateCorrectNextBillingDate(CustomerSubscription $subscription): Carbon
    {
        $billingStartDate = Carbon::parse($subscription->billing_start_date);
        $originalDay = $billingStartDate->day;
        $today = Carbon::today();

        // 請求開始日から今月の請求日を計算
        $currentMonthBillingDate = $today->copy()->day(1); // 今月1日から開始
        $lastDayOfMonth = $currentMonthBillingDate->daysInMonth;

        if ($originalDay > $lastDayOfMonth) {
            // 元の日が今月に存在しない場合は月末
            $currentMonthBillingDate = $currentMonthBillingDate->endOfMonth();
        } else {
            $currentMonthBillingDate = $currentMonthBillingDate->day($originalDay);
        }

        // 今月の請求日がまだ来ていない、または今日なら今月
        if ($currentMonthBillingDate->gte($today)) {
            return $currentMonthBillingDate;
        }

        // すでに過ぎているなら翌月
        $nextMonth = $today->copy()->addMonthNoOverflow();
        $lastDayOfNextMonth = $nextMonth->daysInMonth;

        if ($originalDay > $lastDayOfNextMonth) {
            return $nextMonth->endOfMonth();
        } else {
            return $nextMonth->startOfMonth()->day($originalDay);
        }
    }
}
