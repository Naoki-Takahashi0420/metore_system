<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class LinkCustomersToNagoyaStore extends Seeder
{
    public function run()
    {
        DB::beginTransaction();
        
        try {
            // 名古屋駅前店を取得
            $nagoyaStore = Store::where('name', '目のトレーニング名古屋駅前店')->first();
            
            if (!$nagoyaStore) {
                echo "エラー: 名古屋駅前店が見つかりません。\n";
                return;
            }
            
            echo "名古屋駅前店 ID: {$nagoyaStore->id}\n";
            
            // store_idがNULLの顧客を取得
            $customers = Customer::whereNull('store_id')->get();
            
            echo "店舗未割当の顧客数: {$customers->count()}件\n";
            
            if ($customers->isEmpty()) {
                echo "店舗未割当の顧客はありません。\n";
                return;
            }
            
            // CSVから移行された顧客の特徴で判別
            // - customer_numberが設定されている
            // - 最近作成された
            $csvCustomers = $customers->filter(function ($customer) {
                return !empty($customer->customer_number) && 
                       $customer->created_at >= now()->subDays(1); // 1日以内に作成
            });
            
            echo "CSV移行顧客（推定）: {$csvCustomers->count()}件\n";
            
            // 全ての店舗未割当顧客を名古屋駅前店に紐付け
            $updated = Customer::whereNull('store_id')
                ->update(['store_id' => $nagoyaStore->id]);
            
            DB::commit();
            
            echo "\n=== 紐付け完了 ===\n";
            echo "更新した顧客数: {$updated}件\n";
            echo "全て名古屋駅前店（ID: {$nagoyaStore->id}）に紐付けました。\n";
            
            // 確認: 各店舗の顧客数を表示
            echo "\n=== 店舗別顧客数 ===\n";
            $storeCounts = Customer::join('stores', 'customers.store_id', '=', 'stores.id')
                ->selectRaw('stores.name, COUNT(customers.id) as customer_count')
                ->groupBy('stores.id', 'stores.name')
                ->get();
            
            foreach ($storeCounts as $count) {
                echo "{$count->name}: {$count->customer_count}件\n";
            }
            
        } catch (\Exception $e) {
            DB::rollback();
            echo "エラー: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}