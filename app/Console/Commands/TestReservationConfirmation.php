<?php

namespace App\Console\Commands;

use App\Jobs\SendReservationConfirmationWithFallback;
use App\Models\Reservation;
use App\Services\ReservationConfirmationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TestReservationConfirmation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:reservation-confirmation 
                            {--reservation-id= : 特定の予約IDをテスト}
                            {--dry-run : 実際の送信は行わず、ログのみ出力}
                            {--test-quiet-hours : 静穏時間のテスト}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '予約確認通知フォールバック機能のテスト';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== 予約確認通知フォールバック機能テスト ===');
        
        // 設定値の確認
        $this->info('設定値確認:');
        $this->line('- フォールバック遅延: ' . config('reservation.fallback_delay_minutes', 5) . '分');
        $this->line('- 静穏時間開始: ' . config('reservation.quiet_hours_start', '21:00'));
        $this->line('- 静穏時間終了: ' . config('reservation.quiet_hours_end', '08:00'));
        $this->line('- リトライ回数: ' . config('reservation.retry_before_fallback', 1));
        
        // 静穏時間テスト
        if ($this->option('test-quiet-hours')) {
            $this->testQuietHours();
            return;
        }
        
        // テスト予約の取得
        $reservationId = $this->option('reservation-id');
        if ($reservationId) {
            $reservation = Reservation::with(['customer', 'store', 'menu'])->find($reservationId);
            if (!$reservation) {
                $this->error("予約ID {$reservationId} が見つかりません");
                return;
            }
        } else {
            $reservation = Reservation::with(['customer', 'store', 'menu'])
                ->whereNull('confirmation_sent_at')
                ->whereNotIn('status', ['cancelled', 'canceled'])
                ->first();
                
            if (!$reservation) {
                $this->warn('確認通知未送信の予約が見つかりません');
                return;
            }
        }
        
        $this->info("テスト対象予約:");
        $this->line("- 予約ID: {$reservation->id}");
        $this->line("- 予約番号: {$reservation->reservation_number}");
        $this->line("- 顧客名: {$reservation->customer->last_name} {$reservation->customer->first_name}");
        $this->line("- 電話番号: {$reservation->customer->phone}");
        $this->line("- LINE ID: " . ($reservation->customer->line_user_id ?: '未連携'));
        $this->line("- 確認通知状況: " . ($reservation->confirmation_sent_at ? 
            "送信済み({$reservation->confirmation_method})" : '未送信'));
        
        if ($this->option('dry-run')) {
            $this->warn('ドライランモード: 実際の送信は行いません');
            
            // ドライラン用のサービステスト
            $confirmationService = new ReservationConfirmationService(
                app(\App\Services\LineMessageService::class),
                app(\App\Services\SmsService::class)
            );
            
            $this->info('静穏時間チェック: ' . 
                ($confirmationService->isQuietHours() ? 'はい' : 'いいえ'));
                
            if ($confirmationService->isQuietHours()) {
                $delay = $confirmationService->getDelayUntilNextBusinessHours();
                $nextTime = now()->addSeconds($delay);
                $this->line("次回営業時間まで: {$delay}秒 ({$nextTime->format('Y-m-d H:i:s')})");
            }
            
            return;
        }
        
        // 実際のジョブ実行
        if ($this->confirm('この予約に対して確認通知ジョブを実行しますか？')) {
            $this->info('確認通知ジョブを実行中...');
            
            try {
                SendReservationConfirmationWithFallback::dispatchSync($reservation);
                $this->info('✓ ジョブ実行完了');
                
                // 結果確認
                $reservation->refresh();
                if ($reservation->confirmation_sent_at) {
                    $this->info("✓ 確認通知送信成功: {$reservation->confirmation_method} ({$reservation->confirmation_sent_at})");
                } else {
                    $this->warn('確認通知は送信されませんでした（ログを確認してください）');
                }
            } catch (\Exception $e) {
                $this->error('ジョブ実行エラー: ' . $e->getMessage());
            }
        }
    }
    
    private function testQuietHours()
    {
        $this->info('=== 静穏時間テスト ===');
        
        $confirmationService = new ReservationConfirmationService(
            app(\App\Services\LineMessageService::class),
            app(\App\Services\SmsService::class)
        );
        
        $testTimes = [
            '07:00', '08:00', '09:00', '12:00', 
            '18:00', '20:59', '21:00', '22:00', '23:59'
        ];
        
        $originalTime = now();
        
        foreach ($testTimes as $time) {
            // テスト用に時刻を設定
            Carbon::setTestNow(Carbon::createFromTimeString($time));
            
            $isQuiet = $confirmationService->isQuietHours();
            $status = $isQuiet ? '静穏時間' : '営業時間';
            $this->line("{$time}: {$status}");
        }
        
        // 元の時刻に戻す
        Carbon::setTestNow($originalTime);
    }
}
