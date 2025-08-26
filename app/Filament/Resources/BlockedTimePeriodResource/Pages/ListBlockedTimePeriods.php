<?php

namespace App\Filament\Resources\BlockedTimePeriodResource\Pages;

use App\Filament\Resources\BlockedTimePeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBlockedTimePeriods extends ListRecords
{
    protected static string $resource = BlockedTimePeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}