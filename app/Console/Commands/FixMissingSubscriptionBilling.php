<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CustomerSubscription;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixMissingSubscriptionBilling extends Command
{
    protected $signature = 'subscriptions:fix-missing-billing
                            {--month= : 対象月 (YYYY-MM形式、省略時は当月)}
                            {--dry-run : 実行せずに対象者リストのみ表示}
                            {--force : 確認なしで実行}';

    protected $description = '未計上のサブスク月額料金を一括計上する（バグ修正用）';

    public function handle()
    {
        $monthOption = $this->option('month');
        $isDryRun = $this->option('dry-run');
        $isForce = $this->option('force');

        // 対象月を決定
        if ($monthOption) {
            try {
                $targetMonth = Carbon::createFromFormat('Y-m', $monthOption);
            } catch (\Exception $e) {
                $this->error("無効な月形式です。YYYY-MM形式で入力してください。");
                return self::FAILURE;
            }
        } else {
            $targetMonth = Carbon::now();
        }

        $year = $targetMonth->year;
        $month = $targetMonth->month;
        $today = Carbon::now();

        $this->info("🔍 {$year}年{$month}月の未計上サブスク月額を検索中...");

        // 未計上のサブスクを取得
        $missingBillings = $this->getMissingBillings($year, $month, $today->day);

        if ($missingBillings->isEmpty()) {
            $this->info("✅ 未計上のサブスク月額はありません。");
            return self::SUCCESS;
        }

        // 一覧表示
        $this->info("\n📋 未計上のサブスク月額一覧:");
        $this->table(
            ['ID', '顧客名', 'プラン', '月額', '請求日', '店舗ID'],
            $missingBillings->map(function ($sub) use ($month) {
                return [
                    $sub->id,
                    $sub->customer->last_name . $sub->customer->first_name,
                    mb_substr($sub->plan_name, 0, 20),
                    '¥' . number_format($sub->monthly_price),
                    $month . '/' . Carbon::parse($sub->billing_start_date)->day,
                    $sub->store_id,
                ];
            })->toArray()
        );

        $totalAmount = $missingBillings->sum('monthly_price');
        $this->info("\n合計: {$missingBillings->count()}件 / ¥" . number_format($totalAmount));

        if ($isDryRun) {
            $this->info("\n[DRY RUN] 実際の計上は行いません。");
            $this->info("実行するには --dry-run を外してください。");
            return self::SUCCESS;
        }

        // 確認
        if (!$isForce) {
            if (!$this->confirm("上記 {$missingBillings->count()}件 の売上を計上しますか？")) {
                $this->info("キャンセルしました。");
                return self::SUCCESS;
            }
        }

        // 計上実行
        $successCount = 0;
        $errorCount = 0;

        foreach ($missingBillings as $subscription) {
            try {
                $this->createSubscriptionSale($subscription, $year, $month);
                $successCount++;
                $this->info("  ✅ {$subscription->customer->last_name}{$subscription->customer->first_name} - ¥" . number_format($subscription->monthly_price));
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("  ❌ {$subscription->customer->last_name}{$subscription->customer->first_name} - " . $e->getMessage());
                Log::error("サブスク一括計上エラー", [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("\n📊 実行結果:");
        $this->info("  成功: {$successCount}件");
        if ($errorCount > 0) {
            $this->error("  失敗: {$errorCount}件");
        }

        return self::SUCCESS;
    }

    /**
     * 未計上のサブスクを取得
     */
    protected function getMissingBillings(int $year, int $month, int $maxDay)
    {
        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        // 今月すでに月額計上されたサブスクIDを取得
        $billedSubscriptionIds = Sale::whereBetween('sale_date', [$startOfMonth, $endOfMonth])
            ->whereNotNull('customer_subscription_id')
            ->where('total_amount', '>', 0)
            ->where('notes', 'like', '%サブスク決済%')
            ->pluck('customer_subscription_id')
            ->toArray();

        return CustomerSubscription::with('customer')
            ->where('status', 'active')
            ->where('billing_start_date', '<=', $startOfMonth)
            ->whereRaw("CAST(strftime('%d', billing_start_date) AS INTEGER) <= ?", [$maxDay])
            ->whereNotIn('id', $billedSubscriptionIds)
            ->orderByRaw("CAST(strftime('%d', billing_start_date) AS INTEGER)")
            ->get();
    }

    /**
     * サブスク月額の売上を作成
     */
    protected function createSubscriptionSale(CustomerSubscription $subscription, int $year, int $month)
    {
        $billingDay = Carbon::parse($subscription->billing_start_date)->day;
        $saleDate = Carbon::create($year, $month, $billingDay);

        DB::transaction(function () use ($subscription, $saleDate) {
            // 売上番号生成
            $saleNumber = 'S' . $saleDate->format('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // 売上作成
            $sale = Sale::create([
                'sale_number' => $saleNumber,
                'store_id' => $subscription->store_id,
                'customer_id' => $subscription->customer_id,
                'customer_subscription_id' => $subscription->id,
                'sale_date' => $saleDate,
                'sale_time' => '00:00:00',
                'subtotal' => $subscription->monthly_price,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => $subscription->monthly_price,
                'payment_method' => $subscription->payment_method ?? 'robopay',
                'payment_source' => 'subscription',
                'status' => 'completed',
                'notes' => "サブスク決済（{$subscription->plan_name}）【一括修正】",
                'staff_id' => null,
            ]);

            // 売上明細作成
            SaleItem::create([
                'sale_id' => $sale->id,
                'item_type' => 'subscription',
                'item_name' => $subscription->plan_name,
                'menu_id' => $subscription->menu_id,
                'unit_price' => $subscription->monthly_price,
                'quantity' => 1,
                'amount' => $subscription->monthly_price,
                'subtotal' => $subscription->monthly_price,
                'tax_rate' => 0,
                'tax_amount' => 0,
                'total_amount' => $subscription->monthly_price,
            ]);

            // next_billing_date を翌月に更新
            $originalDay = Carbon::parse($subscription->billing_start_date)->day;
            $nextMonth = $saleDate->copy()->addMonthNoOverflow();
            $lastDayOfNextMonth = $nextMonth->daysInMonth;

            if ($originalDay > $lastDayOfNextMonth) {
                $nextBillingDate = $nextMonth->endOfMonth();
            } else {
                $nextBillingDate = $nextMonth->startOfMonth()->day($originalDay);
            }

            $subscription->update(['next_billing_date' => $nextBillingDate]);

            Log::info("サブスク一括計上完了", [
                'subscription_id' => $subscription->id,
                'sale_id' => $sale->id,
                'sale_date' => $saleDate->format('Y-m-d'),
                'amount' => $subscription->monthly_price,
                'next_billing_date' => $nextBillingDate->format('Y-m-d'),
            ]);
        });
    }
}
