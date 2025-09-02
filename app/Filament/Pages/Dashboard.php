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
            \App\Filament\Widgets\ReservationTimelineWidget::class,
            \App\Filament\Widgets\TodayReservationsWidget::class,
        ];
    }
    
    public function getWidgets(): array
    {
        return [
            // ウィジェットを削除
        ];
    }
}