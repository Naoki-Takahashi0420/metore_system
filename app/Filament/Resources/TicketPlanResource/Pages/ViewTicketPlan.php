<?php

namespace App\Filament\Resources\TicketPlanResource\Pages;

use App\Filament\Resources\TicketPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTicketPlan extends ViewRecord
{
    protected static string $resource = TicketPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
