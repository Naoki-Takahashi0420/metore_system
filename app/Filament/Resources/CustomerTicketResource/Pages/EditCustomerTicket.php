<?php

namespace App\Filament\Resources\CustomerTicketResource\Pages;

use App\Filament\Resources\CustomerTicketResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerTicket extends EditRecord
{
    protected static string $resource = CustomerTicketResource::class;

    protected static bool $shouldCheckUnsavedChanges = true;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
