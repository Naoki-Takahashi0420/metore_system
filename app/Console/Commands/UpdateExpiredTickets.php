<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CustomerTicket;
use Illuminate\Support\Facades\Log;

class UpdateExpiredTickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:update-expired
                            {--dry-run : 実際には更新せずテスト実行}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '有効期限切れの回数券のステータスを自動更新';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('回数券期限切れ更新処理を開始します...');

        if ($dryRun) {
            $this->warn('【テストモード】実際のデータベース更新は行われません。');
        }

        // 期限切れの回数券を取得
        // - ステータスがactive
        // - 有効期限が過去
        // - 残り回数が1回以上（0回の場合はused_upになるべき）
        $expiredTickets = CustomerTicket::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->with(['customer', 'store'])
            ->get();

        $this->info("対象回数券: {$expiredTickets->count()}枚");

        if ($expiredTickets->isEmpty()) {
            $this->info('期限切れの回数券はありません。');
            return Command::SUCCESS;
        }

        $updatedCount = 0;

        foreach ($expiredTickets as $ticket) {
            $customer = $ticket->customer;
            $store = $ticket->store;

            $customerName = $customer
                ? "{$customer->last_name} {$customer->first_name}様"
                : "顧客ID:{$ticket->customer_id}";

            $storeName = $store ? $store->name : "店舗ID:{$ticket->store_id}";

            $this->info("処理中: {$customerName} - {$ticket->plan_name} ({$storeName})");
            $this->line("  有効期限: {$ticket->expires_at->format('Y年m月d日')}");
            $this->line("  残り回数: {$ticket->remaining_count}回");

            if ($dryRun) {
                $this->line("  → [テスト] ステータスを 'expired' に変更予定");
                $updatedCount++;
            } else {
                try {
                    // ステータスを期限切れに更新
                    $ticket->update(['status' => 'expired']);

                    $this->info("  → ステータスを 'expired' に更新しました");
                    $updatedCount++;

                    // ログに記録
                    Log::info('Ticket marked as expired', [
                        'ticket_id' => $ticket->id,
                        'customer_id' => $ticket->customer_id,
                        'store_id' => $ticket->store_id,
                        'plan_name' => $ticket->plan_name,
                        'expires_at' => $ticket->expires_at,
                        'remaining_count' => $ticket->remaining_count,
                    ]);
                } catch (\Exception $e) {
                    $this->error("  → エラー: " . $e->getMessage());

                    Log::error('Failed to mark ticket as expired', [
                        'ticket_id' => $ticket->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        $this->newLine();
        $this->info("処理完了");
        $this->info("更新件数: {$updatedCount}件");

        // ログにも記録
        Log::info('Expired tickets updated', [
            'updated_count' => $updatedCount,
            'dry_run' => $dryRun,
        ]);

        return Command::SUCCESS;
    }
}
