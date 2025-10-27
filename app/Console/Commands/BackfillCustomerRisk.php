<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BackfillCustomerRisk extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer:risk-backfill
                            {--dry-run : 変更せず集計・出力のみ}
                            {--include-overrides : risk_override=true も対象に含める}
                            {--store= : 特定店舗の顧客に限定}
                            {--since-days= : 期間の上書き（任意、未指定ならconfigの既定を使う）}
                            {--limit= : 上限件数（テスト用）}
                            {--only= : risk_flag_sourceの絞り込み（auto|manual-backfill）}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '既存顧客に対して自動判定ロジックを一括適用し、is_blockedを同期';

    /**
     * 実行統計
     */
    protected $stats = [
        'total' => 0,
        'changed_to_blocked' => 0,
        'changed_to_unblocked' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = now();
        $dryRun = $this->option('dry-run');
        $includeOverrides = $this->option('include-overrides');
        $storeId = $this->option('store');
        $sinceDays = $this->option('since-days');
        $limit = $this->option('limit');
        $only = $this->option('only');

        // ログ出力: 実行開始
        Log::info('[BackfillCustomerRisk] 実行開始', [
            'dry_run' => $dryRun,
            'include_overrides' => $includeOverrides,
            'store_id' => $storeId,
            'since_days' => $sinceDays,
            'limit' => $limit,
            'only' => $only,
            'started_at' => $startTime->format('Y-m-d H:i:s'),
        ]);

        // オプション検証
        if ($only && !in_array($only, ['auto', 'manual-backfill'])) {
            $this->error('--only オプションは auto または manual-backfill のみ指定可能です');
            return 1;
        }

        // メモリ対策: クエリログ無効化
        DB::disableQueryLog();

        // クエリ構築
        $query = Customer::query()
            ->select(['id', 'is_blocked', 'risk_override', 'risk_flag_source', 'risk_flagged_at', 'store_id']);

        // フィルタ: risk_override
        if (!$includeOverrides) {
            $query->where('risk_override', false);
        }

        // フィルタ: 店舗
        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        // フィルタ: risk_flag_source
        if ($only) {
            $query->where('risk_flag_source', $only);
        }

        // 上限件数
        if ($limit) {
            $query->limit((int)$limit);
        }

        // 件数カウント
        $totalCount = $query->count();

        $this->info("=== 要注意顧客自動判定バックフィル ===");
        $this->info("モード: " . ($dryRun ? 'ドライラン（変更なし）' : '本実行'));
        $this->info("対象顧客数: {$totalCount} 件");

        if ($storeId) {
            $store = \App\Models\Store::find($storeId);
            $this->info("店舗フィルタ: {$storeId}" . ($store ? " ({$store->name})" : ''));
        }

        if ($sinceDays) {
            $this->info("期間上書き: {$sinceDays} 日");
        }

        if (!$includeOverrides) {
            $this->info("手動上書き顧客: 対象外（risk_override=false のみ）");
        } else {
            $this->warn("手動上書き顧客: 対象に含める（--include-overrides）");
        }

        $this->newLine();

        if ($totalCount === 0) {
            $this->warn('対象顧客が見つかりませんでした。');
            return 0;
        }

        // 確認プロンプト（本実行時のみ）
        if (!$dryRun) {
            if (!$this->confirm("本実行を開始します。{$totalCount} 件の顧客を処理しますか？", true)) {
                $this->info('キャンセルされました。');
                return 0;
            }
        }

        // プログレスバー
        $progressBar = $this->output->createProgressBar($totalCount);
        $progressBar->start();

        // チャンク処理
        $query->orderBy('id')->chunkById(500, function ($customers) use ($dryRun, $sinceDays, $progressBar) {
            foreach ($customers as $customer) {
                try {
                    $this->stats['total']++;

                    // 変更前の状態を保存
                    $oldBlocked = $customer->is_blocked;
                    $oldSource = $customer->risk_flag_source;
                    $oldFlaggedAt = $customer->risk_flagged_at;

                    if ($dryRun) {
                        // ドライラン: シミュレーション（更新しない）
                        $newBlocked = $this->simulateEvaluateRiskStatus($customer, $sinceDays);
                    } else {
                        // 本実行: evaluateRiskStatus() 呼び出し
                        // since-days オプションがあれば一時的に config を上書き
                        if ($sinceDays) {
                            $originalConfig = config('customer_risk.thresholds');
                            config([
                                'customer_risk.thresholds.cancellation.days' => (int)$sinceDays,
                                'customer_risk.thresholds.no_show.days' => (int)$sinceDays,
                                'customer_risk.thresholds.change.days' => (int)$sinceDays,
                            ]);
                        }

                        $customer->evaluateRiskStatus();
                        $customer->refresh();

                        // config を元に戻す
                        if ($sinceDays) {
                            config(['customer_risk.thresholds' => $originalConfig]);
                        }

                        $newBlocked = $customer->is_blocked;
                    }

                    // 変更チェック
                    if ($oldBlocked !== $newBlocked) {
                        if ($newBlocked) {
                            $this->stats['changed_to_blocked']++;
                            $change = 'false → true (自動ON)';
                        } else {
                            $this->stats['changed_to_unblocked']++;
                            $change = 'true → false (自動OFF)';
                        }

                        Log::info('[BackfillCustomerRisk] is_blocked変更', [
                            'customer_id' => $customer->id,
                            'change' => $change,
                            'old_blocked' => $oldBlocked,
                            'new_blocked' => $newBlocked,
                            'old_source' => $oldSource,
                            'new_source' => $customer->risk_flag_source ?? 'auto',
                            'old_flagged_at' => $oldFlaggedAt?->format('Y-m-d H:i:s'),
                            'new_flagged_at' => $customer->risk_flagged_at?->format('Y-m-d H:i:s'),
                            'dry_run' => $dryRun,
                        ]);
                    } else {
                        $this->stats['skipped']++;
                    }

                    $progressBar->advance();

                } catch (\Exception $e) {
                    $this->stats['errors']++;
                    Log::error('[BackfillCustomerRisk] エラー', [
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $progressBar->advance();
                }
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        // 結果サマリ
        $this->info("=== 実行結果 ===");
        $this->info("処理件数: {$this->stats['total']} 件");
        $this->info("自動ON (false → true): {$this->stats['changed_to_blocked']} 件");
        $this->info("自動OFF (true → false): {$this->stats['changed_to_unblocked']} 件");
        $this->info("変更なし: {$this->stats['skipped']} 件");

        if ($this->stats['errors'] > 0) {
            $this->error("エラー: {$this->stats['errors']} 件");
        }

        $endTime = now();
        $duration = $startTime->diffInSeconds($endTime);

        Log::info('[BackfillCustomerRisk] 実行完了', [
            'stats' => $this->stats,
            'started_at' => $startTime->format('Y-m-d H:i:s'),
            'ended_at' => $endTime->format('Y-m-d H:i:s'),
            'duration_seconds' => $duration,
        ]);

        $this->newLine();
        $this->info("実行時間: {$duration} 秒");

        if ($dryRun) {
            $this->warn('※ ドライランのため、データベースは変更されていません。');
        } else {
            $this->info('✓ is_blocked の同期が完了しました。');
        }

        return 0;
    }

    /**
     * ドライラン用: evaluateRiskStatus のシミュレーション
     *
     * @param Customer $customer
     * @param int|null $sinceDays
     * @return bool 新しい is_blocked の値
     */
    protected function simulateEvaluateRiskStatus(Customer $customer, $sinceDays = null): bool
    {
        // 手動上書き中はスキップ
        if ($customer->risk_override) {
            return $customer->is_blocked;
        }

        // 閾値取得（since-days が指定されていればその値を使用）
        $thresholds = config('customer_risk.thresholds');

        if ($sinceDays) {
            $cancellationDays = (int)$sinceDays;
            $noShowDays = (int)$sinceDays;
            $changeDays = (int)$sinceDays;
        } else {
            $cancellationDays = $thresholds['cancellation']['days'] ?? 90;
            $noShowDays = $thresholds['no_show']['days'] ?? 180;
            $changeDays = $thresholds['change']['days'] ?? 60;
        }

        $cancellationThreshold = $thresholds['cancellation']['count'] ?? 2;
        $noShowThreshold = $thresholds['no_show']['count'] ?? 1;
        $changeThreshold = $thresholds['change']['count'] ?? 3;

        // 各カウント取得（店舗都合除外を考慮）
        $cancellationCount = $customer->getRecentReservations('cancelled', $cancellationDays)->count();
        $noShowCount = $customer->getRecentReservations('no_show', $noShowDays)->count();
        $changeCount = $customer->getRecentReservations('changed', $changeDays)->count();

        // 自動判定
        $shouldBlock = false;

        if ($cancellationCount >= $cancellationThreshold) {
            $shouldBlock = true;
        }

        if ($noShowCount >= $noShowThreshold) {
            $shouldBlock = true;
        }

        if ($changeCount >= $changeThreshold) {
            $shouldBlock = true;
        }

        return $shouldBlock;
    }
}
