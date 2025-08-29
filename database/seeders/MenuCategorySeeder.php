<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\Store;
use Illuminate\Database\Seeder;

class MenuCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'ケアコース', 'slug' => 'care-course', 'description' => '目の疲労回復と視力改善を目指すコース', 'sort_order' => 1],
            ['name' => '水素コース', 'slug' => 'hydrogen-course', 'description' => '水素吸入による健康増進コース', 'sort_order' => 2],
            ['name' => 'セットコース', 'slug' => 'set-course', 'description' => 'ケアと水素を組み合わせたお得なコース', 'sort_order' => 3],
            ['name' => 'VRトレーニング', 'slug' => 'vr-training', 'description' => 'VRを使った視力トレーニング', 'sort_order' => 4],
            ['name' => 'オプション', 'slug' => 'options', 'description' => '追加オプションメニュー', 'sort_order' => 5],
        ];

        // 全店舗を取得
        $stores = Store::all();

        foreach ($stores as $store) {
            foreach ($categories as $category) {
                MenuCategory::create([
                    'store_id' => $store->id,
                    'name' => $category['name'],
                    'slug' => $category['slug'] . '-' . $store->id,
                    'description' => $category['description'],
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('Menu categories created for all stores.');
    }
}