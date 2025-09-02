<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reservation;
use App\Models\Store;
use App\Services\SimpleLineService;
use Carbon\Carbon;

class SendLineReminders extends Command
{
    protected $signature = 'line:send-reminders';
    protected $description = '各店舗の設定時刻に予約リマインダーを送信';
    
    public function handle()
    {
        $stores = Store::where('line_enabled', true)
            ->where('line_send_reminder', true)
            ->get();
        
        foreach ($stores as $store) {
            $this->info("店舗: {$store->name} のリマインダー処理開始");
            
            // 店舗の設定時刻を確認
            $reminderTime = Carbon::parse($store->line_reminder_time);
            $daysBefore = $store->line_reminder_days_before ?: 1;
            
            // 現在時刻が送信時刻の範囲内か確認（±5分）
            $now = now();
            if (!$now->between(
                $reminderTime->copy()->subMinutes(5),
                $reminderTime->copy()->addMinutes(5)
            )) {
                $this->info("  送信時刻外のためスキップ");
                continue;
            }
            
            // 対象の予約を取得
            $targetDate = now()->addDays($daysBefore)->format('Y-m-d');
            $reservations = Reservation::where('store_id', $store->id)
                ->whereDate('reservation_date', $targetDate)
                ->where('status', 'confirmed')
                ->whereNull('line_reminder_sent_at')
                ->with(['customer', 'menu'])
                ->get();
            
            $lineService = new SimpleLineService($store);
            $sent = 0;
            
            foreach ($reservations as $reservation) {
                if (!$reservation->customer->line_user_id) {
                    continue;
                }
                
                if ($lineService->sendReminder($reservation)) {
                    $reservation->update(['line_reminder_sent_at' => now()]);
                    $sent++;
                    $this->info("  送信成功: {$reservation->customer->full_name}");
                } else {
                    $this->error("  送信失敗: {$reservation->customer->full_name}");
                }
                
                usleep(200000); // 0.2秒待機
            }
            
            $this->info("  {$sent}件のリマインダーを送信しました");
        }
        
        return Command::SUCCESS;
    }
}