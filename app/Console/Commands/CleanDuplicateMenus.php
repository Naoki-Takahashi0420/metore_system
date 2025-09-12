<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Menu;

class CleanDuplicateMenus extends Command
{
    protected $signature = 'menu:clean-duplicates';
    protected $description = 'テストで作成された重複メニューを削除';

    public function handle()
    {
        $this->info('重複メニューの削除を開始...');
        
        // 削除対象のID（新しく作成された重複メニュー）
        $duplicateIds = [44, 41, 81, 45, 42, 83, 43, 82];
        
        foreach ($duplicateIds as $id) {
            $menu = Menu::find($id);
            if ($menu) {
                $this->info("削除: {$menu->name} (ID: {$id})");
                $menu->delete();
            } else {
                $this->warn("メニューID {$id} は見つかりませんでした");
            }
        }
        
        // 削除後の確認
        $this->info('削除完了。重複チェック...');
        $duplicates = Menu::select('name', 'store_id', 'category_id')
            ->groupBy('name', 'store_id', 'category_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        
        if ($duplicates->isEmpty()) {
            $this->info('✅ 重複メニューは全て削除されました！');
        } else {
            $this->error('❌ まだ重複メニューが残っています：');
            foreach ($duplicates as $dup) {
                $this->line("- {$dup->name} (Store: {$dup->store_id}, Category: {$dup->category_id})");
            }
        }
        
        $totalMenus = Menu::count();
        $this->info("総メニュー数: {$totalMenus}");
        
        return Command::SUCCESS;
    }
}