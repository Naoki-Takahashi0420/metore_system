<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Store;
use App\Services\CustomerNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendFollowUpNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:follow-up 
                            {--days=7,14,30 : フォローアップする日数（カンマ区切り）}
                            {--dry-run : 実際には送信せずテスト実行}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '最後の来店から指定日数経過した顧客にフォローアップ通知を送信';

    private CustomerNotificationService $notificationService;

    public function __construct(CustomerNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $daysString = $this->option('days');
        $dryRun = $this->option('dry-run');
        
        // 日数を配列に変換
        $followUpDays = array_map('intval', explode(',', $daysString));
        
        $this->info('フォローアップ通知処理を開始します...');
        
        if ($dryRun) {
            $this->warn('【テストモード】実際の通知は送信されません。');
        }
        
        $this->info('対象日数: ' . implode(', ', $followUpDays) . '日後');
        
        $totalSent = 0;
        $totalFailed = 0;
        
        foreach ($followUpDays as $days) {
            $this->info("\n=== {$days}日後のフォローアップ処理 ===");
            
            $result = $this->processFollowUpForDays($days, $dryRun);
            $totalSent += $result['sent'];
            $totalFailed += $result['failed'];
        }
        
        $this->newLine();
        $this->info('処理完了');
        $this->info("総送信成功: {$totalSent}件");
        
        if ($totalFailed > 0) {
            $this->warn("総送信失敗: {$totalFailed}件");
        }
        
        // ログにも記録
        Log::info('Follow-up notifications processed', [
            'total_sent' => $totalSent,
            'total_failed' => $totalFailed,
            'follow_up_days' => $followUpDays,
            'dry_run' => $dryRun,
        ]);
        
        return Command::SUCCESS;
    }

    /**
     * 指定日数でのフォローアップ処理
     */
    private function processFollowUpForDays(int $days, bool $dryRun): array
    {
        // 指定日数前の日付を計算
        $targetDate = Carbon::today()->subDays($days);
        
        // 対象顧客を取得：
        // 1. 最後の予約が指定日数前
        // 2. 未来に予約がない
        // 3. ブロック顧客でない
        // 4. 通知設定が有効
        $customers = Customer::whereHas('reservations', function($query) use ($targetDate) {
                // 最後の完了した予約が指定日数前
                $query->where('reservation_date', $targetDate->format('Y-m-d'))
                      ->where('status', 'completed');
            })
            ->whereDoesntHave('reservations', function($query) {
                // 未来に予約がない
                $query->where('reservation_date', '>', now())
                      ->whereIn('status', ['booked', 'confirmed', 'pending']);
            })
            ->where('is_blocked', false)
            ->where(function($query) {
                // LINE通知またはSMS通知が有効
                $query->where(function($q) {
                    $q->whereNotNull('line_user_id')
                      ->where('line_notifications_enabled', true);
                })->orWhere(function($q) {
                    $q->whereNotNull('phone')
                      ->where('sms_notifications_enabled', true);
                });
            })
            ->with(['store', 'reservations' => function($query) {
                $query->orderBy('reservation_date', 'desc')->limit(1);
            }])
            ->get();
        
        $this->info("対象日: {$targetDate->format('Y年n月d日')}");
        $this->info("対象顧客: {$customers->count()}人");
        
        if ($customers->isEmpty()) {
            $this->info('送信対象がありません。');
            return ['sent' => 0, 'failed' => 0];
        }
        
        $sent = 0;
        $failed = 0;
        
        foreach ($customers as $customer) {
            $store = $customer->store;
            if (!$store) {
                $this->error("顧客ID {$customer->id}: 店舗情報が見つかりません");
                $failed++;
                continue;
            }
            
            $this->info("処理中: {$customer->last_name} {$customer->first_name}様 (ID: {$customer->id})");
            
            // 通知可能かチェック
            $canSend = $this->notificationService->canSendNotification($customer, 'follow_up');
            if (!$canSend['any']) {
                $this->warn("  → 通知設定が無効のためスキップ");
                continue;
            }
            
            if ($dryRun) {
                $this->line("  → [テスト] フォローアップ通知送信をスキップ");
                $sent++;
            } else {
                $result = $this->notificationService->sendFollowUpMessage($customer, $store, $days);
                
                if ($result['line'] ?? $result['sms'] ?? false) {
                    $this->info("  → 送信成功");
                    $sent++;
                } else {
                    $this->error("  → 送信失敗");
                    $failed++;
                }
            }
        }
        
        return ['sent' => $sent, 'failed' => $failed];
    }
}
