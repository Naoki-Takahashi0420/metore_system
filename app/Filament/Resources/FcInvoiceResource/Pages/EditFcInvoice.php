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
                ->visible(fn () => auth()->user()?->hasRole('super_admin'))
                ->before(function () {
                    // 紐付いている発注のfc_invoice_idをnullに戻す
                    \App\Models\FcOrder::where('fc_invoice_id', $this->record->id)
                        ->update(['fc_invoice_id' => null]);
                }),
        ];
    }
}
