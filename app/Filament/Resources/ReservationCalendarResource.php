<?php

namespace App\Filament\Resources;

use App\Models\Reservation;
use App\Filament\Resources\ReservationCalendarResource\Pages;
use Filament\Resources\Resource;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class ReservationCalendarResource extends Resource
{
    protected static ?string $model = Reservation::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    
    protected static ?string $navigationLabel = '予約カレンダー';
    
    protected static ?string $modelLabel = '予約カレンダー';
    
    protected static ?string $pluralModelLabel = '予約カレンダー';
    
    protected static ?string $navigationGroup = '予約管理';

    protected static ?int $navigationSort = 99;

    public static function shouldRegisterNavigation(): bool
    {
        return false; // ナビゲーションから非表示
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ViewReservationCalendar::route('/'),
        ];
    }
}