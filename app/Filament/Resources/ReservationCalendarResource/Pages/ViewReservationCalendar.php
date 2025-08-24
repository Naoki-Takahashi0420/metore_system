<?php

namespace App\Filament\Resources\ReservationCalendarResource\Pages;

use App\Filament\Resources\ReservationCalendarResource;
use App\Filament\Widgets\ReservationCalendarWidget;
use App\Filament\Widgets\TodayReservationTimelineWidget;
use Filament\Resources\Pages\Page;

class ViewReservationCalendar extends Page
{
    protected static string $resource = ReservationCalendarResource::class;
    
    protected static string $view = 'filament.resources.reservation-calendar.pages.view-reservation-calendar';
    
    protected ?string $heading = '予約カレンダー';
    
    protected ?string $subheading = '予約状況を一覧で確認できます';
    
    protected function getHeaderWidgets(): array
    {
        return [
            TodayReservationTimelineWidget::class,
            ReservationCalendarWidget::class,
        ];
    }
    
    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }
}