<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Store;
use App\Models\Reservation;
use App\Services\SimpleLineService;
use Carbon\Carbon;

class SendLineFollowup extends Command
{
    protected $signature = 'line:send-followup';
    protected $description = '初回来店後30日・60日のフォローアップメッセージを送信';
    
    public function handle()
    {
        $stores = Store::where('line_enabled', true)
            ->where('line_send_followup', true)
            ->get();
        
        foreach ($stores as $store) {
            $this->info("店舗: {$store->name} のフォローアップ処理開始");
            
            $lineService = new SimpleLineService($store);
            
            // 30日フォローアップ
            $this->send30DayFollowup($store, $lineService);
            
            // 60日フォローアップ
            $this->send60DayFollowup($store, $lineService);
        }
        
        return Command::SUCCESS;
    }
    
    private function send30DayFollowup(Store $store, SimpleLineService $lineService)
    {
        $this->info("  30日フォローアップ処理");
        
        // 30日前に初回来店した顧客を取得
        $targetDate = now()->subDays(30)->format('Y-m-d');
        
        $customers = Customer::whereHas('reservations', function($q) use ($store, $targetDate) {
                $q->where('store_id', $store->id)
                  ->whereDate('reservation_date', $targetDate)
                  ->where('status', 'completed');
            })
            ->whereNotNull('line_user_id')
            ->whereDoesntHave('reservations', function($q) use ($store, $targetDate) {
                // 30日以降に予約がない顧客のみ
                $q->where('store_id', $store->id)
                  ->whereDate('reservation_date', '>', $targetDate);
            })
            ->get();
        
        $sent = 0;
        foreach ($customers as $customer) {
            // 初回来店かチェック
            $visitCount = Reservation::where('customer_id', $customer->id)
                ->where('store_id', $store->id)
                ->where('status', 'completed')
                ->whereDate('reservation_date', '<=', $targetDate)
                ->count();
            
            if ($visitCount != 1) {
                continue; // 初回来店でない場合はスキップ
            }
            
            // 既に30日フォローアップを送信済みかチェック
            $alreadySent = $customer->line_followup_30d_sent_at &&
                Carbon::parse($customer->line_followup_30d_sent_at)->isToday();
            
            if ($alreadySent) {
                continue;
            }
            
            if ($lineService->sendFollowup30Days($customer, $store)) {
                $customer->update(['line_followup_30d_sent_at' => now()]);
                $sent++;
                $this->info("    送信成功: {$customer->full_name}");
            } else {
                $this->error("    送信失敗: {$customer->full_name}");
            }
            
            usleep(200000); // 0.2秒待機
        }
        
        $this->info("  30日フォローアップ: {$sent}件送信");
    }
    
    private function send60DayFollowup(Store $store, SimpleLineService $lineService)
    {
        $this->info("  60日フォローアップ処理");
        
        // 60日前に初回来店した顧客を取得
        $targetDate = now()->subDays(60)->format('Y-m-d');
        
        $customers = Customer::whereHas('reservations', function($q) use ($store, $targetDate) {
                $q->where('store_id', $store->id)
                  ->whereDate('reservation_date', $targetDate)
                  ->where('status', 'completed');
            })
            ->whereNotNull('line_user_id')
            ->whereDoesntHave('reservations', function($q) use ($store, $targetDate) {
                // 60日以降に予約がない顧客のみ
                $q->where('store_id', $store->id)
                  ->whereDate('reservation_date', '>', $targetDate);
            })
            ->get();
        
        $sent = 0;
        foreach ($customers as $customer) {
            // 初回来店かチェック
            $visitCount = Reservation::where('customer_id', $customer->id)
                ->where('store_id', $store->id)
                ->where('status', 'completed')
                ->whereDate('reservation_date', '<=', $targetDate)
                ->count();
            
            if ($visitCount != 1) {
                continue; // 初回来店でない場合はスキップ
            }
            
            // 既に60日フォローアップを送信済みかチェック
            $alreadySent = $customer->line_followup_60d_sent_at &&
                Carbon::parse($customer->line_followup_60d_sent_at)->isToday();
            
            if ($alreadySent) {
                continue;
            }
            
            if ($lineService->sendFollowup60Days($customer, $store)) {
                $customer->update(['line_followup_60d_sent_at' => now()]);
                $sent++;
                $this->info("    送信成功: {$customer->full_name}");
            } else {
                $this->error("    送信失敗: {$customer->full_name}");
            }
            
            usleep(200000); // 0.2秒待機
        }
        
        $this->info("  60日フォローアップ: {$sent}件送信");
    }
}