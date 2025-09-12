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
    protected $description = '初回来店後7日・15日のフォローアップメッセージを送信';
    
    public function handle()
    {
        $stores = Store::where('line_enabled', true)
            ->where('line_send_followup', true)
            ->get();
        
        foreach ($stores as $store) {
            $this->info("店舗: {$store->name} のフォローアップ処理開始");
            
            $lineService = new SimpleLineService($store);
            
            // 7日フォローアップ
            $this->send7DayFollowup($store, $lineService);
            
            // 15日フォローアップ
            $this->send15DayFollowup($store, $lineService);
        }
        
        return Command::SUCCESS;
    }
    
    private function send7DayFollowup(Store $store, SimpleLineService $lineService)
    {
        $this->info("  7日フォローアップ処理");
        
        // 7日前に初回来店した顧客を取得
        $targetDate = now()->subDays(7)->format('Y-m-d');
        
        $customers = Customer::whereHas('reservations', function($q) use ($store, $targetDate) {
                $q->where('store_id', $store->id)
                  ->whereDate('reservation_date', $targetDate)
                  ->where('status', 'completed');
            })
            ->whereNotNull('line_user_id')
            ->whereDoesntHave('reservations', function($q) use ($store, $targetDate) {
                // 7日以降に予約がない顧客のみ
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
            
            // 既に7日フォローアップを送信済みかチェック
            $alreadySent = $customer->line_followup_7d_sent_at &&
                Carbon::parse($customer->line_followup_7d_sent_at)->isToday();
            
            if ($alreadySent) {
                continue;
            }
            
            if ($lineService->sendFollowup7Days($customer, $store)) {
                $customer->update(['line_followup_7d_sent_at' => now()]);
                $sent++;
                $this->info("    送信成功: {$customer->full_name}");
            } else {
                $this->error("    送信失敗: {$customer->full_name}");
            }
            
            usleep(200000); // 0.2秒待機
        }
        
        $this->info("  7日フォローアップ: {$sent}件送信");
    }
    
    private function send15DayFollowup(Store $store, SimpleLineService $lineService)
    {
        $this->info("  15日フォローアップ処理");
        
        // 15日前に初回来店した顧客を取得
        $targetDate = now()->subDays(15)->format('Y-m-d');
        
        $customers = Customer::whereHas('reservations', function($q) use ($store, $targetDate) {
                $q->where('store_id', $store->id)
                  ->whereDate('reservation_date', $targetDate)
                  ->where('status', 'completed');
            })
            ->whereNotNull('line_user_id')
            ->whereDoesntHave('reservations', function($q) use ($store, $targetDate) {
                // 15日以降に予約がない顧客のみ
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
            
            // 既に15日フォローアップを送信済みかチェック
            $alreadySent = $customer->line_followup_15d_sent_at &&
                Carbon::parse($customer->line_followup_15d_sent_at)->isToday();
            
            if ($alreadySent) {
                continue;
            }
            
            if ($lineService->sendFollowup15Days($customer, $store)) {
                $customer->update(['line_followup_15d_sent_at' => now()]);
                $sent++;
                $this->info("    送信成功: {$customer->full_name}");
            } else {
                $this->error("    送信失敗: {$customer->full_name}");
            }
            
            usleep(200000); // 0.2秒待機
        }
        
        $this->info("  15日フォローアップ: {$sent}件送信");
    }
}