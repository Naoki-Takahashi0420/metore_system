<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class AssignUserColorsSeeder extends Seeder
{
    /**
     * 利用可能な色のリスト
     */
    protected $availableColors = [
        '#ef4444', // 赤
        '#f97316', // オレンジ
        '#eab308', // 黄
        '#84cc16', // ライム
        '#22c55e', // 緑
        '#14b8a6', // ティール
        '#06b6d4', // シアン
        '#3b82f6', // 青
        '#6366f1', // インディゴ
        '#8b5cf6', // バイオレット
        '#a855f7', // パープル
        '#d946ef', // フクシア
        '#ec4899', // ピンク
        '#f43f5e', // ローズ
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::whereNull('theme_color')->get();

        $colorIndex = 0;
        foreach ($users as $user) {
            $user->theme_color = $this->availableColors[$colorIndex % count($this->availableColors)];
            $user->save();
            $colorIndex++;
        }

        $this->command->info('Assigned colors to ' . $users->count() . ' users.');
    }
}