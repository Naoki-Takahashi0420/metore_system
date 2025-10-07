<?php

namespace App\Filament\Resources\CustomerTicketResource\Pages;

use App\Filament\Resources\CustomerTicketResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerTickets extends ListRecords
{
    protected static string $resource = CustomerTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
