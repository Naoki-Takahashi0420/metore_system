<?php

namespace App\Filament\Resources\ShiftResource\Pages;

use App\Filament\Resources\ShiftResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShifts extends ListRecords
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('time-tracking')
                ->label('勤怠入力')
                ->icon('heroicon-o-clock')
                ->url(ShiftResource::getUrl('time-tracking'))
                ->color('success')
                ->size('lg'),
            Actions\CreateAction::make()
                ->label('新規シフト登録'),
            Actions\Action::make('calendar')
                ->label('カレンダー表示')
                ->icon('heroicon-o-calendar')
                ->url(ShiftResource::getUrl('calendar'))
                ->color('primary'),
        ];
    }
    
    protected function getHeaderWidgets(): array
    {
        return [
            ShiftResource\Widgets\ShiftStats::class,
        ];
    }
}