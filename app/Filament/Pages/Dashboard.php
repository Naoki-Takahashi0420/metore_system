<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    
    protected static string $view = 'filament::pages.dashboard';
    
    public function getHeaderWidgets(): array
    {
        return [
            // ヘッダーウィジェットは使用しない
        ];
    }

    public function getWidgets(): array
    {
        // ダッシュボード専用のウィジェットのみ表示（FCウィジェットは除外）
        return [
            \App\Filament\Widgets\ReservationTimelineWidget::class,     // 1. 予約タイムラインテーブル
            \App\Filament\Widgets\PaymentFailedAlertWidget::class,      // 2. 決済失敗アラート
            \App\Filament\Widgets\AnnouncementWidget::class,            // 3. 本部からのお知らせ
            \App\Filament\Widgets\TodayReservationsWidget::class,       // 4. 予約一覧
            \App\Filament\Widgets\ReservationCalendarWidget::class,     // 5. 予約カレンダー
            \App\Filament\Widgets\ShiftManagementLinkWidget::class,     // 6. 本日のシフト状況
            \App\Filament\Widgets\SubscriptionStatsWidget::class,       // 7. 統計情報
        ];
    }
}