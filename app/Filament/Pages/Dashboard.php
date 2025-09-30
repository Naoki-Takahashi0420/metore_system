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
            \App\Filament\Widgets\PaymentFailedAlertWidget::class,      // 2. 決済失敗アラート
            \App\Filament\Widgets\TodayReservationsWidget::class,       // 3. 予約一覧
            \App\Filament\Widgets\ReservationCalendarWidget::class,     // 4. 予約カレンダー
            \App\Filament\Widgets\ShiftManagementLinkWidget::class,     // 5. 本日のシフト状況
            \App\Filament\Widgets\SubscriptionStatsWidget::class,       // 6. 統計情報
        ];
    }
}