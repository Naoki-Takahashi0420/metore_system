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
        return [
            \App\Filament\Widgets\ReservationTimelineWidget::class,     // 1. 予約タイムラインテーブル
            \App\Filament\Widgets\TodayReservationsWidget::class,       // 2. 予約一覧
            \App\Filament\Widgets\ReservationCalendarWidget::class,     // 3. 予約カレンダー
            \App\Filament\Widgets\ShiftManagementLinkWidget::class,     // 4. 本日のシフト状況
            \App\Filament\Widgets\SubscriptionStatsWidget::class,       // 5. 統計情報
        ];
    }
}