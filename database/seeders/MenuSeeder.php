<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Store;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stores = Store::all();

        foreach ($stores as $store) {
            // 基本メニュー
            Menu::create([
                'store_id' => $store->id,
                'category' => '視力検査',
                'name' => '初回視力検査・カウンセリング',
                'description' => '詳細な視力検査と個別カウンセリングを行います。',
                'price' => 5000,
                'duration' => 60,
                'is_available' => true,
                'sort_order' => 1,
                'tags' => ['初回限定', '人気'],
            ]);

            Menu::create([
                'store_id' => $store->id,
                'category' => '視力検査',
                'name' => '定期視力検査',
                'description' => '3ヶ月ごとの定期検査です。',
                'price' => 3000,
                'duration' => 30,
                'is_available' => true,
                'sort_order' => 2,
            ]);

            // トレーニングメニュー
            Menu::create([
                'store_id' => $store->id,
                'category' => 'トレーニング',
                'name' => 'ベーシック視力トレーニング',
                'description' => '基本的な視力改善トレーニングプログラムです。',
                'price' => 8000,
                'duration' => 45,
                'is_available' => true,
                'sort_order' => 3,
                'tags' => ['おすすめ'],
            ]);

            Menu::create([
                'store_id' => $store->id,
                'category' => 'トレーニング',
                'name' => 'アドバンスド視力トレーニング',
                'description' => '専門的な機器を使用した集中トレーニングです。',
                'price' => 12000,
                'duration' => 60,
                'is_available' => true,
                'max_daily_quantity' => 5,
                'sort_order' => 4,
                'tags' => ['専門', '効果的'],
            ]);

            Menu::create([
                'store_id' => $store->id,
                'category' => 'トレーニング',
                'name' => 'VRトレーニングプログラム',
                'description' => 'VR技術を活用した最新の視力改善プログラムです。',
                'price' => 15000,
                'duration' => 90,
                'is_available' => true,
                'max_daily_quantity' => 3,
                'sort_order' => 5,
                'tags' => ['最新', 'VR'],
            ]);

            // コースメニュー
            Menu::create([
                'store_id' => $store->id,
                'category' => 'コース',
                'name' => '1ヶ月集中改善コース',
                'description' => '週2回×4週間の集中改善プログラムです。',
                'price' => 50000,
                'duration' => 60,
                'is_available' => true,
                'sort_order' => 6,
                'options' => [
                    'sessions' => 8,
                    'validity_days' => 35,
                ],
                'tags' => ['人気', 'お得'],
            ]);

            Menu::create([
                'store_id' => $store->id,
                'category' => 'コース',
                'name' => '3ヶ月スタンダードコース',
                'description' => '週1回×12週間のスタンダードプログラムです。',
                'price' => 80000,
                'duration' => 60,
                'is_available' => true,
                'sort_order' => 7,
                'options' => [
                    'sessions' => 12,
                    'validity_days' => 100,
                ],
                'tags' => ['標準', '効果的'],
            ]);
        }
    }
}