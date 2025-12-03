<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Announcement;
use Livewire\WithPagination;

class Announcements extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static string $view = 'filament.pages.announcements';

    protected static ?string $navigationLabel = 'お知らせ一覧';

    protected static ?string $title = '本部からのお知らせ';

    protected static ?string $slug = 'announcements-list';

    protected static bool $shouldRegisterNavigation = false; // ナビゲーションには表示しない

    public $filter = 'all'; // all, unread, read
    public $tab = 'general'; // general（一般）, order（発注通知）

    public function mount(): void
    {
        // 初期化処理
        $this->tab = request()->get('tab', 'general');
    }

    public function getAnnouncements()
    {
        $user = auth()->user();

        $query = Announcement::query()
            ->active()
            ->forUser($user);

        // タブによるフィルタリング
        if ($this->tab === 'order') {
            $query->where('type', 'order_notification');
        } else {
            // デフォルトは一般的なお知らせ
            $query->where(function($q) {
                $q->where('type', 'general')
                  ->orWhereNull('type'); // 既存データ互換性のため
            });
        }

        // 既読・未読フィルタ適用
        if ($this->filter === 'unread') {
            $query->whereDoesntHave('reads', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        } elseif ($this->filter === 'read') {
            $query->whereHas('reads', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $query->orderBy('published_at', 'desc');

        return $query->paginate(10);
    }

    public function markAsRead(int $announcementId): void
    {
        $user = auth()->user();

        \App\Models\AnnouncementRead::firstOrCreate([
            'announcement_id' => $announcementId,
            'user_id' => $user->id,
        ], [
            'read_at' => now()
        ]);

        // ページを再レンダリング
        $this->dispatch('announcement-read');
    }

    public function markAllAsRead(): void
    {
        $user = auth()->user();

        $announcements = Announcement::query()
            ->active()
            ->forUser($user);

        // 現在のタブに応じてフィルタ
        if ($this->tab === 'order') {
            $announcements->where('type', 'order_notification');
        } else {
            $announcements->where(function($q) {
                $q->where('type', 'general')
                  ->orWhereNull('type');
            });
        }

        $announcements = $announcements
            ->whereDoesntHave('reads', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->get();

        foreach ($announcements as $announcement) {
            \App\Models\AnnouncementRead::firstOrCreate([
                'announcement_id' => $announcement->id,
                'user_id' => $user->id,
            ], [
                'read_at' => now()
            ]);
        }

        $this->dispatch('all-announcements-read');
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->filter = 'all'; // タブ切り替え時にフィルタをリセット
        $this->resetPage(); // ページネーションをリセット
    }

    public static function canAccess(): bool
    {
        // 全ユーザーがアクセス可能（お知らせを確認できる）
        return auth()->check();
    }
}
