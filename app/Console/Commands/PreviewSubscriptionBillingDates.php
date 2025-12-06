<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CustomerSubscription;
use Carbon\Carbon;

class PreviewSubscriptionBillingDates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:preview-billing-dates
                            {--limit=10 : 表示件数（0で全件表示）}
                            {--show-all : 正常なものも含めて全件表示}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'サブスク契約の次回請求日を確認（プレビューのみ、更新はしません）';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 サブスク契約の次回請求日チェック開始...');
        $this->newLine();

        $showAll = $this->option('show-all');
        $limit = (int) $this->option('limit');

        // アクティブなサブスク契約を取得
        $subscriptions = CustomerSubscription::with('customer')
            ->where('status', 'active')
            ->whereNotNull('billing_start_date')
            ->get();

        $totalCount = $subscriptions->count();
        $outdatedCount = 0;
        $needsUpdateCount = 0;
        $changes = [];

        foreach ($subscriptions as $subscription) {
            $currentNextBilling = $subscription->next_billing_date
                ? Carbon::parse($subscription->next_billing_date)
                : null;

            $correctNextBilling = $this->calculateCorrectNextBillingDate($subscription);

            $isOutdated = $currentNextBilling && $currentNextBilling->isPast();
            $needsUpdate = !$currentNextBilling ||
                          $currentNextBilling->format('Y-m-d') !== $correctNextBilling->format('Y-m-d');

            if ($isOutdated) {
                $outdatedCount++;
            }

            if ($needsUpdate) {
                $needsUpdateCount++;
                $changes[] = [
                    'subscription' => $subscription,
                    'current' => $currentNextBilling,
                    'correct' => $correctNextBilling,
                    'days_diff' => $currentNextBilling
                        ? $currentNextBilling->diffInDays(Carbon::today(), false)
                        : null,
                ];
            }
        }

        // サマリー表示
        $this->info("📊 サマリー");
        $this->table(
            ['項目', '件数'],
            [
                ['総サブスク契約数（アクティブ）', $totalCount],
                ['次回請求日が過去', $outdatedCount],
                ['更新が必要', $needsUpdateCount],
                ['正常', $totalCount - $needsUpdateCount],
            ]
        );
        $this->newLine();

        // 更新が必要なものを表示
        if ($needsUpdateCount > 0) {
            // 遅延日数でソート（降順）
            usort($changes, function($a, $b) {
                $aDiff = $a['days_diff'] ?? -999999;
                $bDiff = $b['days_diff'] ?? -999999;
                return $bDiff <=> $aDiff;
            });

            $displayCount = $limit > 0 ? min($limit, count($changes)) : count($changes);

            $this->warn("⚠️  更新が必要な契約（{$displayCount}件を表示）");
            $this->newLine();

            $tableData = [];
            for ($i = 0; $i < $displayCount; $i++) {
                $change = $changes[$i];
                $subscription = $change['subscription'];
                $customer = $subscription->customer;

                $customerName = $customer
                    ? $customer->last_name . $customer->first_name
                    : '不明';

                $currentStr = $change['current']
                    ? $change['current']->format('Y-m-d')
                    : '未設定';

                $correctStr = $change['correct']->format('Y-m-d');

                $diffStr = $change['days_diff'] !== null
                    ? ($change['days_diff'] > 0 ? "+{$change['days_diff']}日遅れ" : "{$change['days_diff']}日")
                    : '-';

                $tableData[] = [
                    $subscription->id,
                    $customerName,
                    $subscription->billing_start_date->format('Y-m-d'),
                    $currentStr,
                    $correctStr,
                    $diffStr,
                ];
            }

            $this->table(
                ['ID', '顧客名', '請求開始日', '現在の次回請求日', '正しい次回請求日', '差分'],
                $tableData
            );

            if ($limit > 0 && count($changes) > $limit) {
                $remaining = count($changes) - $limit;
                $this->info("（他 {$remaining}件...）");
                $this->comment("全件表示: php artisan subscriptions:preview-billing-dates --limit=0");
            }
        } else {
            $this->info('✅ すべての契約の次回請求日は正常です！');
        }

        $this->newLine();
        $this->comment('💡 このコマンドはプレビューのみです。実際のデータは変更されていません。');

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
