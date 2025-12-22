<?php

namespace App\Jobs;

use App\Models\BroadcastMessage;
use App\Models\Customer;
use App\Services\SimpleLineService;
use App\Services\EmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBroadcastMessage implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public BroadcastMessage $broadcastMessage;

    /**
     * リトライ回数
     */
    public $tries = 1;

    /**
     * タイムアウト（秒）
     */
    public $timeout = 600; // 10分

    /**
     * Create a new job instance.
     */
    public function __construct(BroadcastMessage $broadcastMessage)
    {
        $this->broadcastMessage = $broadcastMessage;
    }

    /**
     * Execute the job.
     */
    public function handle(SimpleLineService $lineService, EmailService $emailService): void
    {
        $broadcast = $this->broadcastMessage;

        // 送信可能かチェック
        if (!$broadcast->canSend()) {
            Log::warning('BroadcastMessage: 送信不可状態', [
                'broadcast_id' => $broadcast->id,
                'status' => $broadcast->status
            ]);
            return;
        }

        // 予約時刻チェック
        if (!$broadcast->isScheduledTimeReached()) {
            Log::info('BroadcastMessage: 予約時刻未到達、再スケジュール', [
                'broadcast_id' => $broadcast->id,
                'scheduled_at' => $broadcast->scheduled_at
            ]);
            // 予約時刻まで遅延して再実行
            $this->release($broadcast->scheduled_at->diffInSeconds(now()));
            return;
        }

        Log::info('BroadcastMessage: 送信開始', [
            'broadcast_id' => $broadcast->id,
            'store_id' => $broadcast->store_id,
            'subject' => $broadcast->subject
        ]);

        // ステータスを送信中に更新
        $broadcast->update(['status' => BroadcastMessage::STATUS_SENDING]);

        $store = $broadcast->store;
        $customers = $broadcast->getTargetCustomers();

        $totalRecipients = $customers->count();
        $lineCount = 0;
        $emailCount = 0;
        $successCount = 0;
        $failedCount = 0;

        foreach ($customers as $customer) {
            try {
                $sent = false;

                // LINE連携済みの場合はLINEで送信
                if ($customer->line_user_id && $store->line_enabled && $store->line_channel_access_token) {
                    $result = $lineService->sendMessage(
                        $store,
                        $customer->line_user_id,
                        $this->buildLineMessage($broadcast, $customer)
                    );

                    if ($result) {
                        $sent = true;
                        $lineCount++;
                        $successCount++;
                        Log::debug('BroadcastMessage: LINE送信成功', [
                            'broadcast_id' => $broadcast->id,
                            'customer_id' => $customer->id
                        ]);
                    }
                }

                // LINE送信できなかった場合、メールで送信
                if (!$sent && $customer->email) {
                    $result = $emailService->send(
                        $customer->email,
                        $broadcast->subject,
                        $this->buildEmailMessage($broadcast, $customer, $store)
                    );

                    if ($result) {
                        $sent = true;
                        $emailCount++;
                        $successCount++;
                        Log::debug('BroadcastMessage: メール送信成功', [
                            'broadcast_id' => $broadcast->id,
                            'customer_id' => $customer->id
                        ]);
                    }
                }

                if (!$sent) {
                    $failedCount++;
                    Log::warning('BroadcastMessage: 送信失敗', [
                        'broadcast_id' => $broadcast->id,
                        'customer_id' => $customer->id,
                        'has_line' => !empty($customer->line_user_id),
                        'has_email' => !empty($customer->email)
                    ]);
                }

                // レート制限対策（100ms待機）
                usleep(100000);

            } catch (\Exception $e) {
                $failedCount++;
                Log::error('BroadcastMessage: 送信エラー', [
                    'broadcast_id' => $broadcast->id,
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // 結果を更新
        $broadcast->update([
            'status' => $failedCount === $totalRecipients
                ? BroadcastMessage::STATUS_FAILED
                : BroadcastMessage::STATUS_SENT,
            'sent_at' => now(),
            'total_recipients' => $totalRecipients,
            'line_count' => $lineCount,
            'email_count' => $emailCount,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
        ]);

        Log::info('BroadcastMessage: 送信完了', [
            'broadcast_id' => $broadcast->id,
            'total' => $totalRecipients,
            'line' => $lineCount,
            'email' => $emailCount,
            'success' => $successCount,
            'failed' => $failedCount
        ]);
    }

    /**
     * LINEメッセージを構築
     */
    private function buildLineMessage(BroadcastMessage $broadcast, Customer $customer): string
    {
        $message = $broadcast->message;

        // 変数置換
        $replacements = [
            '{{customer_name}}' => "{$customer->last_name} {$customer->first_name}",
            '{{customer_last_name}}' => $customer->last_name ?? '',
            '{{customer_first_name}}' => $customer->first_name ?? '',
            '{{store_name}}' => $broadcast->store->name ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    /**
     * メールメッセージを構築
     */
    private function buildEmailMessage(BroadcastMessage $broadcast, Customer $customer, $store): string
    {
        $message = $this->buildLineMessage($broadcast, $customer);

        // メール用にフォーマット
        return $message . "\n\n--\n{$store->name}\n" . ($store->phone ?? '');
    }

    /**
     * ジョブ失敗時の処理
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BroadcastMessage: ジョブ失敗', [
            'broadcast_id' => $this->broadcastMessage->id,
            'error' => $exception->getMessage()
        ]);

        $this->broadcastMessage->update([
            'status' => BroadcastMessage::STATUS_FAILED
        ]);
    }
}
