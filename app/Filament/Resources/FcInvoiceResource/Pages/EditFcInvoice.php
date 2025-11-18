<?php

namespace App\Filament\Resources\FcInvoiceResource\Pages;

use App\Filament\Resources\FcInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFcInvoice extends EditRecord
{
    protected static string $resource = FcInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->status === 'draft'),
        ];
    }
}
