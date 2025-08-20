<?php

namespace App\Filament\Resources\ShiftSchedules\Pages;

use App\Filament\Resources\ShiftSchedules\ShiftScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShiftSchedules extends ListRecords
{
    protected static string $resource = ShiftScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
