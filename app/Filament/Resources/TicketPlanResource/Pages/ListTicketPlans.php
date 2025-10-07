<?php

namespace App\Filament\Resources\TicketPlanResource\Pages;

use App\Filament\Resources\TicketPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTicketPlans extends ListRecords
{
    protected static string $resource = TicketPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
