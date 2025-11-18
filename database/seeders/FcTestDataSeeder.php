<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\FcProductCategory;
use App\Models\FcProduct;
use Illuminate\Database\Seeder;

class FcTestDataSeeder extends Seeder
{
    /**
     * FC本部管理システムのテストデータを作成
     */
    public function run(): void
    {
        // 1. 本部店舗を作成
        $headquarters = Store::create([
            'name' => 'メトレ本部',
            'fc_type' => 'headquarters',
            'headquarters_store_id' => null,
            'code' => 'HQ001',
            'postal_code' => '150-0001',
            'prefecture' => '東京都',
            'city' => '渋谷区',
            'address' => '神宮前1-1-1',
            'phone' => '03-1234-5678',
            'email' => 'headquarters@meno-training.com',
            'is_active' => true,
        ]);

        $this->command->info("本部店舗を作成しました: {$headquarters->name}");

        // 2. FC加盟店を作成
        $fcStore1 = Store::create([
            'name' => 'メトレ横浜店',
            'fc_type' => 'fc_store',
            'headquarters_store_id' => $headquarters->id,
            'code' => 'FC001',
            'postal_code' => '220-0001',
            'prefecture' => '神奈川県',
            'city' => '横浜市西区',
            'address' => '北幸2-2-2',
            'phone' => '045-123-4567',
            'email' => 'yokohama@meno-training.com',
            'is_active' => true,
        ]);

        $fcStore2 = Store::create([
            'name' => 'メトレ大阪店',
            'fc_type' => 'fc_store',
            'headquarters_store_id' => $headquarters->id,
            'code' => 'FC002',
            'postal_code' => '530-0001',
            'prefecture' => '大阪府',
            'city' => '大阪市北区',
            'address' => '梅田3-3-3',
            'phone' => '06-1234-5678',
            'email' => 'osaka@meno-training.com',
            'is_active' => true,
        ]);

        $this->command->info("FC加盟店を作成しました: {$fcStore1->name}, {$fcStore2->name}");

        // 3. 商品カテゴリを作成
        $category1 = FcProductCategory::create([
            'headquarters_store_id' => $headquarters->id,
            'name' => 'トレーニング機器',
            'description' => '目のトレーニング用機器',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $category2 = FcProductCategory::create([
            'headquarters_store_id' => $headquarters->id,
            'name' => 'サプリメント',
            'description' => '目の健康をサポートするサプリメント',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $category3 = FcProductCategory::create([
            'headquarters_store_id' => $headquarters->id,
            'name' => '販促物',
            'description' => 'チラシ、ポスター等の販促資材',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        $this->command->info("商品カテゴリを作成しました: {$category1->name}, {$category2->name}, {$category3->name}");

        // 4. 商品を作成
        $products = [
            // トレーニング機器
            [
                'headquarters_store_id' => $headquarters->id,
                'category_id' => $category1->id,
                'sku' => 'EQ-001',
                'name' => 'アイトレーナーPRO',
                'description' => '最新型の目のトレーニング機器。視力回復に効果的。',
                'unit_price' => 150000,
                'tax_rate' => 10,
                'unit' => '台',
                'stock_quantity' => 50,
                'min_order_quantity' => 1,
                'is_active' => true,
            ],
            [
                'headquarters_store_id' => $headquarters->id,
                'category_id' => $category1->id,
                'sku' => 'EQ-002',
                'name' => 'ビジョンチャート',
                'description' => '視力測定用チャート',
                'unit_price' => 5000,
                'tax_rate' => 10,
                'unit' => '枚',
                'stock_quantity' => 200,
                'min_order_quantity' => 5,
                'is_active' => true,
            ],
            // サプリメント
            [
                'headquarters_store_id' => $headquarters->id,
                'category_id' => $category2->id,
                'sku' => 'SP-001',
                'name' => 'ルテインプラス',
                'description' => 'ルテイン配合サプリメント（90粒入り）',
                'unit_price' => 3500,
                'tax_rate' => 8,
                'unit' => '箱',
                'stock_quantity' => 500,
                'min_order_quantity' => 10,
                'is_active' => true,
            ],
            [
                'headquarters_store_id' => $headquarters->id,
                'category_id' => $category2->id,
                'sku' => 'SP-002',
                'name' => 'ブルーベリーアイ',
                'description' => 'ブルーベリーエキス配合（60粒入り）',
                'unit_price' => 2800,
                'tax_rate' => 8,
                'unit' => '箱',
                'stock_quantity' => 300,
                'min_order_quantity' => 10,
                'is_active' => true,
            ],
            // 販促物
            [
                'headquarters_store_id' => $headquarters->id,
                'category_id' => $category3->id,
                'sku' => 'PR-001',
                'name' => '店頭ポスターA2',
                'description' => '店頭掲示用ポスター（A2サイズ）',
                'unit_price' => 500,
                'tax_rate' => 10,
                'unit' => '枚',
                'stock_quantity' => 1000,
                'min_order_quantity' => 10,
                'is_active' => true,
            ],
            [
                'headquarters_store_id' => $headquarters->id,
                'category_id' => $category3->id,
                'sku' => 'PR-002',
                'name' => 'パンフレット3つ折り',
                'description' => 'サービス案内パンフレット（100部セット）',
                'unit_price' => 8000,
                'tax_rate' => 10,
                'unit' => 'セット',
                'stock_quantity' => 100,
                'min_order_quantity' => 1,
                'is_active' => true,
            ],
        ];

        foreach ($products as $productData) {
            FcProduct::create($productData);
        }

        $this->command->info("商品を" . count($products) . "点作成しました");

        $this->command->info("\n=== FCテストデータ作成完了 ===");
        $this->command->info("本部店舗ID: {$headquarters->id}");
        $this->command->info("FC店舗ID: {$fcStore1->id}, {$fcStore2->id}");
        $this->command->info("\n管理画面からFC商品・発注・請求書を管理できます。");
    }
}
