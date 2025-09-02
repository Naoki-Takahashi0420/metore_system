<?php

namespace App\Filament\Resources\ReservationLineResource\Pages;

use App\Filament\Resources\ReservationLineResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewReservationLine extends ViewRecord
{
    protected static string $resource = ReservationLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}