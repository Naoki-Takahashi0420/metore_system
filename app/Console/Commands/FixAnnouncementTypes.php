<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use Illuminate\Console\Command;

class FixAnnouncementTypes extends Command
{
    protected $signature = 'announcements:fix-types';
    protected $description = 'FC関連のお知らせにorder_notificationタイプを設定';

    public function handle()
    {
        $this->info('=== お知らせタイプ修正 ===');

        // FC関連のキーワード
        $fcKeywords = ['発注', '納品', '請求', '発送', '入金'];

        $announcements = Announcement::whereNull('type')
            ->orWhere('type', '')
            ->get();

        $updated = 0;
        foreach ($announcements as $announcement) {
            $isFcRelated = false;
            foreach ($fcKeywords as $keyword) {
                if (str_contains($announcement->title, $keyword)) {
                    $isFcRelated = true;
                    break;
                }
            }

            if ($isFcRelated) {
                $announcement->update(['type' => Announcement::TYPE_ORDER_NOTIFICATION]);
                $this->line("  [{$announcement->id}] {$announcement->title} -> order_notification");
                $updated++;
            } else {
                $announcement->update(['type' => Announcement::TYPE_GENERAL]);
                $this->line("  [{$announcement->id}] {$announcement->title} -> general");
                $updated++;
            }
        }

        $this->info("=== 完了: {$updated}件更新 ===");
        return 0;
    }
}
