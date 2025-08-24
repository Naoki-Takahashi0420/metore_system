<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Store;
use Illuminate\Database\Seeder;

class UpsellMenuSeeder extends Seeder
{
    public function run(): void
    {
        $stores = Store::all();
        
        foreach ($stores as $store) {
            // アップセル用のオプションメニューを作成
            $upsellMenus = [
                [
                    'name' => 'アイケア・リラクゼーション',
                    'description' => '目の疲れを和らげる特別なマッサージとケアです。',
                    'upsell_description' => 'お疲れの目をさらにケアしませんか？',
                    'price' => 2000,
                    'duration' => 20,
                    'category' => 'eye_care',
                    'display_order' => 1,
                ],
                [
                    'name' => 'プレミアム視力検査',
                    'description' => 'より詳細な視力検査で正確な状態を把握します。',
                    'upsell_description' => 'より詳しい検査でお客様の目の状態を正確に把握しませんか？',
                    'price' => 3000,
                    'duration' => 30,
                    'category' => 'vision_training',
                    'display_order' => 2,
                ],
                [
                    'name' => 'ブルーライトカット相談',
                    'description' => 'デジタル機器による目の負担を軽減するアドバイス。',
                    'upsell_description' => 'スマホやPCで疲れた目に、ブルーライト対策はいかがですか？',
                    'price' => 1500,
                    'duration' => 15,
                    'category' => 'consultation',
                    'display_order' => 3,
                ],
                [
                    'name' => 'VR体験プラス',
                    'description' => '最新のVR技術で楽しみながら視力トレーニング。',
                    'upsell_description' => '楽しみながら目を鍛える最新VR体験はいかがでしょうか？',
                    'price' => 2500,
                    'duration' => 25,
                    'category' => 'vr_training',
                    'display_order' => 4,
                ],
                [
                    'name' => 'ホームケア指導',
                    'description' => 'ご自宅でできる目のケア方法を詳しくお教えします。',
                    'upsell_description' => 'ご自宅でも続けられる目のケア方法をお教えします',
                    'price' => 1000,
                    'duration' => 10,
                    'category' => 'consultation',
                    'display_order' => 5,
                ]
            ];

            foreach ($upsellMenus as $index => $menuData) {
                Menu::create([
                    'store_id' => $store->id,
                    'name' => $menuData['name'],
                    'description' => $menuData['description'],
                    'price' => $menuData['price'],
                    'duration' => $menuData['duration'],
                    'category' => $menuData['category'],
                    'is_available' => true,
                    'is_option' => false, // メインメニューではない
                    'show_in_upsell' => true, // アップセル画面に表示
                    'upsell_description' => $menuData['upsell_description'],
                    'display_order' => $menuData['display_order'],
                    'sort_order' => 100 + $index, // メインメニューより後に表示
                ]);
            }
        }

        $this->command->info('アップセルメニューを作成しました！');
        $this->command->info('- アイケア・リラクゼーション (¥2,000)');
        $this->command->info('- プレミアム視力検査 (¥3,000)');  
        $this->command->info('- ブルーライトカット相談 (¥1,500)');
        $this->command->info('- VR体験プラス (¥2,500)');
        $this->command->info('- ホームケア指導 (¥1,000)');
    }
}