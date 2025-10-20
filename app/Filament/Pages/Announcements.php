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

    public function mount(): void
    {
        // 初期化処理
    }

    public function getAnnouncements()
    {
        $user = auth()->user();

        $query = Announcement::query()
            ->active()
            ->forUser($user)
            ->orderBy('published_at', 'desc');

        // フィルタ適用
        if ($this->filter === 'unread') {
            $query->whereDoesntHave('reads', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        } elseif ($this->filter === 'read') {
            $query->whereHas('reads', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

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
            ->forUser($user)
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

    public static function canAccess(): bool
    {
        // スーパーアドミン以外のユーザーにアクセスを許可
        return !auth()->user()?->hasRole('super_admin') ?? true;
    }
}
