<?php

namespace App\Filament\Resources\TicketPlanResource\Pages;

use App\Filament\Resources\TicketPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicketPlan extends CreateRecord
{
    protected static string $resource = TicketPlanResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
