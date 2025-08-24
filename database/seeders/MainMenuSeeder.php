<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Store;
use Illuminate\Database\Seeder;

class MainMenuSeeder extends Seeder
{
    public function run(): void
    {
        $stores = Store::all();
        
        foreach ($stores as $store) {
            // メインメニューを作成（is_option = false, show_in_upsell = false）
            $mainMenus = [
                [
                    'name' => '基本視力検査',
                    'description' => '視力・視野・色覚などの基本的な検査を行います。',
                    'price' => 3000,
                    'duration' => 30,
                    'category' => 'vision_training',
                    'display_order' => 1,
                ],
                [
                    'name' => 'VR視力トレーニング',
                    'description' => '最新のVR技術を使用した視力改善トレーニングです。',
                    'price' => 5000,
                    'duration' => 45,
                    'category' => 'vr_training',
                    'display_order' => 2,
                ],
                [
                    'name' => '総合アイケアコース',
                    'description' => '検査・トレーニング・ケアを含む総合的なコースです。',
                    'price' => 8000,
                    'duration' => 60,
                    'category' => 'eye_care',
                    'display_order' => 3,
                ],
                [
                    'name' => '眼精疲労回復プログラム',
                    'description' => 'デスクワークで疲れた目を癒す特別プログラムです。',
                    'price' => 4500,
                    'duration' => 40,
                    'category' => 'eye_care',
                    'display_order' => 4,
                ],
                [
                    'name' => '子供向け視力検査',
                    'description' => 'お子様向けの楽しい視力検査プログラムです。',
                    'price' => 2500,
                    'duration' => 20,
                    'category' => 'vision_training',
                    'display_order' => 5,
                ],
                [
                    'name' => '視力改善カウンセリング',
                    'description' => '専門家による個別カウンセリングです。',
                    'price' => 3500,
                    'duration' => 30,
                    'category' => 'consultation',
                    'display_order' => 6,
                ],
            ];

            foreach ($mainMenus as $index => $menuData) {
                Menu::create([
                    'store_id' => $store->id,
                    'name' => $menuData['name'],
                    'description' => $menuData['description'],
                    'price' => $menuData['price'],
                    'duration' => $menuData['duration'],
                    'category' => $menuData['category'],
                    'is_available' => true,
                    'is_option' => false,  // メインメニュー
                    'show_in_upsell' => false,  // アップセルには表示しない
                    'display_order' => $menuData['display_order'],
                    'sort_order' => $index,
                ]);
            }
        }

        $this->command->info('メインメニューを作成しました！');
        $this->command->info('- 基本視力検査 (¥3,000)');
        $this->command->info('- VR視力トレーニング (¥5,000)');
        $this->command->info('- 総合アイケアコース (¥8,000)');
        $this->command->info('- 眼精疲労回復プログラム (¥4,500)');
        $this->command->info('- 子供向け視力検査 (¥2,500)');
        $this->command->info('- 視力改善カウンセリング (¥3,500)');
    }
}