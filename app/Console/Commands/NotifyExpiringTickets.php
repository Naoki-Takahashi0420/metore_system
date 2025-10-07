<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CustomerTicket;
use App\Services\CustomerNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NotifyExpiringTickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:notify-expiring
                            {--days=7 : 何日前に通知を送るか}
                            {--dry-run : 実際には送信せずテスト実行}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '有効期限が近い回数券の通知を顧客に送信';

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
        $daysBefore = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info('回数券期限切れ通知処理を開始します...');

        if ($dryRun) {
            $this->warn('【テストモード】実際の通知は送信されません。');
        }

        // 通知対象日を計算（今日から指定日数後）
        $expiryDate = Carbon::today()->addDays($daysBefore);

        // 対象の回数券を取得
        // - 有効期限が対象日
        // - ステータスがactive
        // - 残り回数が1回以上
        // - まだ期限切れ通知を送信していない
        $tickets = CustomerTicket::where('status', 'active')
            ->whereDate('expires_at', $expiryDate)
            ->where('remaining_count', '>', 0)
            ->whereNull('expiry_notified_at')
            ->with(['customer', 'store', 'ticketPlan'])
            ->get();

        $this->info("対象期限日: {$expiryDate->format('Y年m月d日')}");
        $this->info("対象回数券: {$tickets->count()}枚");

        if ($tickets->isEmpty()) {
            $this->info('通知対象がありません。');
            return Command::SUCCESS;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($tickets as $ticket) {
            $customer = $ticket->customer;
            $store = $ticket->store;

            if (!$customer) {
                $this->error("回数券ID {$ticket->id}: 顧客情報が見つかりません");
                $failCount++;
                continue;
            }

            if (!$store) {
                $this->error("回数券ID {$ticket->id}: 店舗情報が見つかりません");
                $failCount++;
                continue;
            }

            // 通知設定を確認
            $canSend = $this->notificationService->canSendNotification($customer, 'ticket_expiry');

            if (!$canSend['any']) {
                $this->info("顧客 {$customer->last_name} {$customer->first_name}様: 通知が無効になっています");
                continue;
            }

            // ブロック顧客チェック
            if ($customer->is_blocked) {
                $this->warn("顧客 {$customer->last_name} {$customer->first_name}様: ブロック顧客のためスキップ");
                continue;
            }

            // 通知メッセージを作成
            $message = $this->buildNotificationMessage($customer, $ticket, $store, $daysBefore);

            $this->info("送信中: {$customer->last_name} {$customer->first_name}様 - {$ticket->plan_name}");

            if ($dryRun) {
                $this->line("  → [テスト] 通知送信をスキップ");
                $this->line("  → メッセージ内容:");
                $this->line($message);
                $successCount++;
            } else {
                try {
                    // 通知を送信（LINE優先、SMS代替）
                    $result = $this->notificationService->sendNotification(
                        $customer,
                        $store,
                        $message,
                        'ticket_expiry'
                    );

                    if ($result['line'] || $result['sms']) {
                        $this->info("  → 送信成功 (" . ($result['line'] ? 'LINE' : 'SMS') . ")");

                        // 通知送信日時を記録
                        $ticket->update([
                            'expiry_notified_at' => now()
                        ]);

                        $successCount++;
                    } else {
                        $this->error("  → 送信失敗");
                        $failCount++;
                    }
                } catch (\Exception $e) {
                    $this->error("  → エラー: " . $e->getMessage());
                    $failCount++;

                    Log::error('Ticket expiry notification failed', [
                        'ticket_id' => $ticket->id,
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        $this->newLine();
        $this->info("処理完了");
        $this->info("成功: {$successCount}件");

        if ($failCount > 0) {
            $this->warn("失敗: {$failCount}件");
        }

        // ログにも記録
        Log::info('Ticket expiry notifications sent', [
            'expiry_date' => $expiryDate->format('Y-m-d'),
            'days_before' => $daysBefore,
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'dry_run' => $dryRun,
        ]);

        return Command::SUCCESS;
    }

    /**
     * 通知メッセージを作成
     */
    private function buildNotificationMessage(
        $customer,
        CustomerTicket $ticket,
        $store,
        int $daysBefore
    ): string {
        $customerName = "{$customer->last_name} {$customer->first_name}様";
        $expiryDate = Carbon::parse($ticket->expires_at)->format('Y年n月j日');
        $remainingCount = $ticket->remaining_count;
        $planName = $ticket->plan_name;

        if ($daysBefore <= 3) {
            // 3日前以内 - 緊急の通知
            $message = "【回数券期限のお知らせ】\n{$customerName}\n\n";
            $message .= "お持ちの回数券の有効期限が間もなく到来いたします。\n\n";
            $message .= "━━━━━━━━━━━━━━\n";
            $message .= "プラン: {$planName}\n";
            $message .= "残り回数: {$remainingCount}回\n";
            $message .= "有効期限: {$expiryDate}\n";
            $message .= "━━━━━━━━━━━━━━\n\n";
            $message .= "期限までにご利用いただけないと、残り回数が無効となってしまいます。\n\n";
            $message .= "お早めのご予約をお待ちしております。\n\n";
            $message .= "{$store->name}\n{$store->phone}";
        } else {
            // 7日前 - 通常の通知
            $message = "【回数券期限のお知らせ】\n{$customerName}\n\n";
            $message .= "お持ちの回数券の有効期限が近づいております。\n\n";
            $message .= "━━━━━━━━━━━━━━\n";
            $message .= "プラン: {$planName}\n";
            $message .= "残り回数: {$remainingCount}回\n";
            $message .= "有効期限: {$expiryDate}\n";
            $message .= "━━━━━━━━━━━━━━\n\n";
            $message .= "期限内にぜひご利用ください。\nご予約をお待ちしております。\n\n";
            $message .= "{$store->name}\n{$store->phone}";
        }

        return $message;
    }
}
