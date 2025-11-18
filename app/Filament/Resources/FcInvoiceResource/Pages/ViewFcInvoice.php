<?php

namespace App\Filament\Resources\FcInvoiceResource\Pages;

use App\Filament\Resources\FcInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFcInvoice extends ViewRecord
{
    protected static string $resource = FcInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
