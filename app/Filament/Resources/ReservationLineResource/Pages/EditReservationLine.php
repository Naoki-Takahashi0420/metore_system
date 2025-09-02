<?php

namespace App\Filament\Resources\ReservationLineResource\Pages;

use App\Filament\Resources\ReservationLineResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReservationLine extends EditRecord
{
    protected static string $resource = ReservationLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}