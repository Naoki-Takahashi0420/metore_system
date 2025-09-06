<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReservations extends ListRecords
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('新規予約')
                ->icon('heroicon-o-plus-circle'),
            Actions\Action::make('quick_phone_reservation')
                ->label('電話予約を追加')
                ->icon('heroicon-o-phone')
                ->color('success')
                ->url(fn () => static::getResource()::getUrl('create') . '?source=phone')
                ->extraAttributes([
                    'title' => '電話で受けた予約を素早く登録'
                ]),
        ];
    }
}