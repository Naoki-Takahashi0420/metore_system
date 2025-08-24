<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Store;
use Illuminate\Database\Seeder;

class CustomerTypeMenuSeeder extends Seeder
{
    public function run(): void
    {
        $stores = Store::all();
        
        foreach ($stores as $store) {
            // 新規顧客限定メニュー
            Menu::create([
                'store_id' => $store->id,
                'name' => '【初回限定】体験コース',
                'description' => '初めての方限定！基本検査＋VR体験のお得なセット',
                'price' => 1980,
                'duration' => 40,
                'category' => 'vision_training',
                'is_available' => true,
                'show_in_upsell' => false,
                'customer_type_restriction' => 'new',  // 新規顧客のみ
                'medical_record_only' => false,
                'display_order' => 1,
            ]);
            
            // 既存顧客限定・カルテからのみ
            Menu::create([
                'store_id' => $store->id,
                'name' => '【リピーター様限定】プレミアムケア',
                'description' => '2回目以降の方専用の特別ケアプログラム',
                'price' => 6500,
                'duration' => 60,
                'category' => 'eye_care',
                'is_available' => true,
                'show_in_upsell' => false,
                'customer_type_restriction' => 'existing',  // 既存顧客のみ
                'medical_record_only' => true,  // カルテからのみ予約可能
                'display_order' => 2,
            ]);
            
            // 既存顧客限定・通常予約OK
            Menu::create([
                'store_id' => $store->id,
                'name' => 'フォローアップ検査',
                'description' => '前回の検査から改善状況を確認',
                'price' => 2500,
                'duration' => 30,
                'category' => 'vision_training',
                'is_available' => true,
                'show_in_upsell' => false,
                'customer_type_restriction' => 'existing',  // 既存顧客のみ
                'medical_record_only' => false,  // 通常予約からも可能
                'display_order' => 3,
            ]);
            
            // VIP限定・カルテからのみ
            Menu::create([
                'store_id' => $store->id,
                'name' => '【VIP】完全個別コンサルティング',
                'description' => 'VIP会員様限定の完全個別対応プログラム',
                'price' => 15000,
                'duration' => 90,
                'category' => 'consultation',
                'is_available' => true,
                'show_in_upsell' => false,
                'customer_type_restriction' => 'vip',  // VIPのみ
                'medical_record_only' => true,  // カルテからのみ
                'display_order' => 4,
            ]);
        }

        $this->command->info('顧客タイプ別メニューを作成しました！');
        $this->command->info('- 【初回限定】体験コース (¥1,980) - 新規顧客のみ');
        $this->command->info('- 【リピーター様限定】プレミアムケア (¥6,500) - カルテからのみ');
        $this->command->info('- フォローアップ検査 (¥2,500) - 既存顧客のみ');
        $this->command->info('- 【VIP】完全個別コンサルティング (¥15,000) - VIP・カルテのみ');
    }
}