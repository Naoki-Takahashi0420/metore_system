<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Store;
use Illuminate\Database\Seeder;

class MenuSeederWithCategory extends Seeder
{
    public function run(): void
    {
        // 銀座店のメニューを作成（テスト用）
        $ginzaStore = Store::where('name', 'LIKE', '%銀座%')->first();
        
        if (!$ginzaStore) {
            $this->command->error('銀座店が見つかりません。StoreSeederを先に実行してください。');
            return;
        }

        // 銀座店のケアコースカテゴリーを取得
        $careCategory = MenuCategory::where('store_id', $ginzaStore->id)
            ->where('slug', 'LIKE', 'care-course%')
            ->first();

        $hydrogenCategory = MenuCategory::where('store_id', $ginzaStore->id)
            ->where('slug', 'LIKE', 'hydrogen-course%')
            ->first();

        $setCategory = MenuCategory::where('store_id', $ginzaStore->id)
            ->where('slug', 'LIKE', 'set-course%')
            ->first();

        if (!$careCategory || !$hydrogenCategory || !$setCategory) {
            $this->command->error('カテゴリーが見つかりません。MenuCategorySeederを先に実行してください。');
            return;
        }

        // ケアコースメニュー
        $careMenus = [
            [
                'name' => '視力回復ケア 30分',
                'duration_minutes' => 30,
                'price' => 3500,
                'description' => '基本的な視力回復プログラム。初めての方におすすめです。',
                'customer_type_restriction' => 'all',
            ],
            [
                'name' => '視力回復ケア 50分',
                'duration_minutes' => 50,
                'price' => 5000,
                'description' => 'しっかりとした視力回復プログラム。じっくりケアしたい方に。',
                'customer_type_restriction' => 'all',
            ],
            [
                'name' => '視力回復ケア 80分',
                'duration_minutes' => 80,
                'price' => 8000,
                'description' => '特別集中ケアプログラム。本格的な改善を目指す方に。',
                'customer_type_restriction' => 'existing',
                'is_subscription_only' => false,
            ],
            [
                'name' => 'プレミアムケア 80分',
                'duration_minutes' => 80,
                'price' => 12000,
                'description' => 'サブスク会員様限定の特別プログラム。',
                'customer_type_restriction' => 'existing',
                'is_subscription_only' => true,
            ],
        ];

        foreach ($careMenus as $index => $menuData) {
            Menu::create([
                'store_id' => $ginzaStore->id,
                'category_id' => $careCategory->id,
                'name' => $menuData['name'],
                'description' => $menuData['description'],
                'price' => $menuData['price'],
                'duration' => $menuData['duration_minutes'], // 互換性のため
                'duration_minutes' => $menuData['duration_minutes'],
                'is_available' => true,
                'is_visible_to_customer' => true,
                'is_subscription_only' => $menuData['is_subscription_only'] ?? false,
                'customer_type_restriction' => $menuData['customer_type_restriction'],
                'medical_record_only' => false,
                'sort_order' => $index,
                'display_order' => $index,
            ]);
        }

        // 水素コースメニュー
        $hydrogenMenus = [
            [
                'name' => '水素吸入 30分',
                'duration_minutes' => 30,
                'price' => 2000,
                'description' => '高濃度水素吸入でリフレッシュ',
            ],
            [
                'name' => '水素吸入 50分',
                'duration_minutes' => 50,
                'price' => 3000,
                'description' => 'たっぷり水素吸入でデトックス',
            ],
        ];

        foreach ($hydrogenMenus as $index => $menuData) {
            Menu::create([
                'store_id' => $ginzaStore->id,
                'category_id' => $hydrogenCategory->id,
                'name' => $menuData['name'],
                'description' => $menuData['description'],
                'price' => $menuData['price'],
                'duration' => $menuData['duration_minutes'],
                'duration_minutes' => $menuData['duration_minutes'],
                'is_available' => true,
                'is_visible_to_customer' => true,
                'customer_type_restriction' => 'all',
                'sort_order' => $index,
                'display_order' => $index,
            ]);
        }

        // セットコースメニュー
        $setMenus = [
            [
                'name' => 'ケア＋水素セット 50分',
                'duration_minutes' => 50,
                'price' => 6500,
                'description' => 'ケア30分＋水素20分のお得なセット',
            ],
            [
                'name' => 'ケア＋水素セット 80分',
                'duration_minutes' => 80,
                'price' => 9500,
                'description' => 'ケア50分＋水素30分のお得なセット',
            ],
        ];

        foreach ($setMenus as $index => $menuData) {
            Menu::create([
                'store_id' => $ginzaStore->id,
                'category_id' => $setCategory->id,
                'name' => $menuData['name'],
                'description' => $menuData['description'],
                'price' => $menuData['price'],
                'duration' => $menuData['duration_minutes'],
                'duration_minutes' => $menuData['duration_minutes'],
                'is_available' => true,
                'is_visible_to_customer' => true,
                'customer_type_restriction' => 'all',
                'sort_order' => $index,
                'display_order' => $index,
            ]);
        }

        $this->command->info('銀座店のメニューを作成しました。');
    }
}