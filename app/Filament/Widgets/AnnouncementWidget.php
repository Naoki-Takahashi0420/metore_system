<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Announcement;

class AnnouncementWidget extends Widget
{
    protected static string $view = 'filament.widgets.announcement';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1; // 最上部に表示

    public $announcements = [];
    public $unreadCount = 0;

    public function mount(): void
    {
        $this->loadAnnouncements();
    }

    public function loadAnnouncements(): void
    {
        $user = auth()->user();

        // まず未読のお知らせを取得（新しい順）
        $unreadAnnouncements = Announcement::query()
            ->active()
            ->forUser($user)
            ->whereDoesntHave('reads', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->orderBy('published_at', 'desc')
            ->limit(3)
            ->get();

        // 未読が3件未満なら、既読も含めて3件になるように補充
        if ($unreadAnnouncements->count() < 3) {
            $needed = 3 - $unreadAnnouncements->count();

            $readAnnouncements = Announcement::query()
                ->active()
                ->forUser($user)
                ->whereHas('reads', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->orderBy('published_at', 'desc')
                ->limit($needed)
                ->get();

            $this->announcements = $unreadAnnouncements->merge($readAnnouncements);
        } else {
            $this->announcements = $unreadAnnouncements;
        }

        // 未読件数を取得
        $this->unreadCount = Announcement::query()
            ->active()
            ->forUser($user)
            ->whereDoesntHave('reads', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->count();
    }

    public function markAsRead(int $announcementId): void
    {
        $user = auth()->user();

        // 既読レコードを作成（重複は防止される）
        \App\Models\AnnouncementRead::firstOrCreate([
            'announcement_id' => $announcementId,
            'user_id' => $user->id,
        ], [
            'read_at' => now()
        ]);

        // お知らせを再読み込み
        $this->loadAnnouncements();

        // 成功通知
        $this->dispatch('announcement-read');
    }

    public static function canView(): bool
    {
        // 全ユーザーに表示
        return true;
    }
}
