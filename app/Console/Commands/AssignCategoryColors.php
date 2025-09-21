<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MenuCategory;

class AssignCategoryColors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'categories:assign-colors';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign colors to existing menu categories based on their names';

    /**
     * カテゴリー名と色のマッピング
     */
    private $colorMapping = [
        // メインコース
        'ケアコース' => '#3b82f6',      // 青系
        '水素コース' => '#8b5cf6',      // 紫系
        'トレーニングコース' => '#f97316', // オレンジ系

        // その他のパターン
        '眼精疲労ケアコース' => '#3b82f6',
        '瞳うるおいコース' => '#f97316',
        '脳疲労撃退コース' => '#22c55e',    // 緑系
        '目元リフトアップコース' => '#ef4444', // 赤系
        'オプション' => '#eab308',          // 黄系
        'コースに悩んだ方はこちら' => '#3b82f6',
        '指名のみコース' => '#6b7280',      // グレー系
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('メニューカテゴリーへの色割り当てを開始...');

        $categories = MenuCategory::where('is_active', true)->get();

        if ($categories->isEmpty()) {
            $this->info('アクティブなカテゴリーが見つかりません。');
            return;
        }

        $updatedCount = 0;
        $colorPatterns = [
            '#3b82f6', // 青系
            '#8b5cf6', // 紫系
            '#f97316', // オレンジ系
            '#22c55e', // 緑系
            '#ef4444', // 赤系
            '#eab308', // 黄系
        ];

        foreach ($categories as $index => $category) {
            // 名前ベースのマッピングを優先
            if (isset($this->colorMapping[$category->name])) {
                $color = $this->colorMapping[$category->name];
            } else {
                // マッピングにない場合は順番に色を割り当て
                $color = $colorPatterns[$index % count($colorPatterns)];
            }

            $category->color = $color;
            $category->save();

            $this->line("カテゴリー「{$category->name}」に色「{$color}」を割り当てました。");
            $updatedCount++;
        }

        $this->info("完了: {$updatedCount}個のカテゴリーに色を割り当てました。");
    }
}
