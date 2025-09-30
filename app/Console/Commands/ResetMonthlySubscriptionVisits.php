<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CustomerSubscription;
use Carbon\Carbon;

/**
 * @deprecated このコマンドは不要になりました
 *
 * current_month_visitsは予約データから動的計算されるため、
 * リセット処理は不要です。削除予定。
 *
 * 理由:
 * - service_start_date基準で期間を計算
 * - 予約テーブルから実際の来店回数をカウント
 * - 契約応当日が顧客ごとに異なるため、一括リセットは不適切
 */
class ResetMonthlySubscriptionVisits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:reset-monthly
                            {--dry-run : 実行せずに対象を表示するだけ}
                            {--force : 確認なしで実行}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '[非推奨] サブスクリプションの月次利用回数をリセット（動的計算に変更されたため不要）';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $isForce = $this->option('force');
        
        $this->info('サブスクリプション月次リセット処理を開始します...');
        
        // アクティブなサブスクリプションを取得
        $subscriptions = CustomerSubscription::active()
            ->whereNotNull('monthly_limit')
            ->get();
        
        if ($subscriptions->isEmpty()) {
            $this->info('リセット対象のサブスクリプションはありません。');
            return Command::SUCCESS;
        }
        
        $this->info("対象サブスクリプション数: {$subscriptions->count()}件");
        
        // リセット対象のサブスクリプションを判定
        $toReset = collect();
        $today = Carbon::now();
        
        foreach ($subscriptions as $subscription) {
            $shouldReset = false;
            $reason = '';
            
            // reset_dayが設定されている場合
            if ($subscription->reset_day) {
                if ($today->day == $subscription->reset_day) {
                    $shouldReset = true;
                    $reason = "リセット日（毎月{$subscription->reset_day}日）";
                }
            } 
            // billing_start_dateから判定
            elseif ($subscription->billing_start_date) {
                $billingDay = $subscription->billing_start_date->day;
                if ($today->day == $billingDay) {
                    $shouldReset = true;
                    $reason = "請求開始日基準（毎月{$billingDay}日）";
                }
            }
            // デフォルトは月初（1日）
            else {
                if ($today->day == 1) {
                    $shouldReset = true;
                    $reason = "月初リセット（デフォルト）";
                }
            }
            
            if ($shouldReset) {
                $toReset->push([
                    'subscription' => $subscription,
                    'reason' => $reason,
                    'current_visits' => $subscription->current_month_visits,
                    'monthly_limit' => $subscription->monthly_limit,
                ]);
            }
        }
        
        if ($toReset->isEmpty()) {
            $this->info('本日リセット対象のサブスクリプションはありません。');
            return Command::SUCCESS;
        }
        
        // リセット対象を表示
        $this->table(
            ['ID', '顧客', 'プラン', '現在の利用回数', '月間上限', 'リセット理由'],
            $toReset->map(function ($item) {
                $sub = $item['subscription'];
                return [
                    $sub->id,
                    $sub->customer->last_name . ' ' . $sub->customer->first_name,
                    $sub->plan_name,
                    $item['current_visits'] . '回',
                    $item['monthly_limit'] . '回',
                    $item['reason'],
                ];
            })
        );
        
        if ($isDryRun) {
            $this->info('【ドライラン】実際のリセットは実行されませんでした。');
            return Command::SUCCESS;
        }
        
        // 確認
        if (!$isForce) {
            if (!$this->confirm("{$toReset->count()}件のサブスクリプションをリセットしますか？")) {
                $this->info('処理をキャンセルしました。');
                return Command::SUCCESS;
            }
        }
        
        // リセット実行
        $resetCount = 0;
        foreach ($toReset as $item) {
            $subscription = $item['subscription'];
            
            // 利用履歴を記録（オプション）
            $this->logUsageHistory($subscription);
            
            // リセット
            $subscription->update([
                'current_month_visits' => 0,
                'last_reset_at' => now(),
            ]);
            
            $resetCount++;
            $this->info("ID: {$subscription->id} - リセット完了");
        }
        
        $this->info("✅ {$resetCount}件のサブスクリプションをリセットしました。");
        
        return Command::SUCCESS;
    }
    
    /**
     * 利用履歴をログに記録
     */
    private function logUsageHistory($subscription)
    {
        // 必要に応じて履歴テーブルに記録
        \Log::info('Subscription monthly reset', [
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer_id,
            'month' => now()->format('Y-m'),
            'visits' => $subscription->current_month_visits,
            'limit' => $subscription->monthly_limit,
            'usage_rate' => $subscription->monthly_limit > 0 
                ? round(($subscription->current_month_visits / $subscription->monthly_limit) * 100, 1) . '%'
                : 'N/A',
        ]);
    }
}