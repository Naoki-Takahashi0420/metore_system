<?php

namespace App\Filament\Resources\FcInvoiceItemTemplateResource\Pages;

use App\Filament\Resources\FcInvoiceItemTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageFcInvoiceItemTemplates extends ManageRecords
{
    protected static string $resource = FcInvoiceItemTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
