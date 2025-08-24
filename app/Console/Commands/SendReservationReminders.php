<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MedicalRecord;
use App\Models\Store;
use App\Services\Sms\SmsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendReservationReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send 
                            {--days=3 : 何日前にリマインダーを送るか}
                            {--dry-run : 実際には送信せずテスト実行}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '次回来院予定日が近い顧客にSMSリマインダーを送信';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $daysBefore = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        
        $this->info('予約リマインダー送信処理を開始します...');
        
        if ($dryRun) {
            $this->warn('【テストモード】実際のSMSは送信されません。');
        }
        
        // リマインダー対象日を計算
        $targetDate = Carbon::today()->addDays($daysBefore);
        
        // 対象のカルテを取得
        $medicalRecords = MedicalRecord::whereNotNull('next_visit_date')
            ->whereDate('next_visit_date', $targetDate)
            ->where('reservation_status', 'pending') // まだ予約されていない
            ->whereNull('reminder_sent_at') // リマインダー未送信
            ->with('customer')
            ->get();
        
        $this->info("対象日: {$targetDate->format('Y年m月d日')}");
        $this->info("対象件数: {$medicalRecords->count()}件");
        
        if ($medicalRecords->isEmpty()) {
            $this->info('送信対象がありません。');
            return Command::SUCCESS;
        }
        
        $smsService = new SmsService();
        $successCount = 0;
        $failCount = 0;
        
        foreach ($medicalRecords as $record) {
            $customer = $record->customer;
            
            if (!$customer) {
                $this->error("カルテID {$record->id}: 顧客情報が見つかりません");
                $failCount++;
                continue;
            }
            
            if (!$customer->phone) {
                $this->warn("顧客 {$customer->last_name} {$customer->first_name}様: 電話番号が登録されていません");
                $failCount++;
                continue;
            }
            
            // SMS通知設定を確認
            if (!$customer->sms_notifications_enabled) {
                $this->info("顧客 {$customer->last_name} {$customer->first_name}様: SMS通知がオフになっています");
                continue;
            }
            
            // ブロック顧客チェック
            if ($customer->is_blocked) {
                $this->warn("顧客 {$customer->last_name} {$customer->first_name}様: ブロック顧客のためスキップ");
                continue;
            }
            
            $this->info("送信中: {$customer->last_name} {$customer->first_name}様 ({$customer->phone})");
            
            if ($dryRun) {
                $this->line("  → [テスト] SMS送信をスキップ");
                $successCount++;
            } else {
                $success = $smsService->sendReservationReminder($customer, $record);
                
                if ($success) {
                    $this->info("  → 送信成功");
                    $successCount++;
                } else {
                    $this->error("  → 送信失敗");
                    $failCount++;
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
        Log::info('Reservation reminders sent', [
            'target_date' => $targetDate->format('Y-m-d'),
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'dry_run' => $dryRun,
        ]);
        
        return Command::SUCCESS;
    }
}