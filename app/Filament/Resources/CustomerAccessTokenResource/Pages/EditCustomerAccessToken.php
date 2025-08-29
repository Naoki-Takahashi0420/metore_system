<?php

namespace App\Filament\Resources\CustomerAccessTokenResource\Pages;

use App\Filament\Resources\CustomerAccessTokenResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerAccessToken extends EditRecord
{
    protected static string $resource = CustomerAccessTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
