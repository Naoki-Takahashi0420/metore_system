<?php

namespace App\Filament\Resources\FcOrderResource\Pages;

use App\Filament\Resources\FcOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFcOrder extends ViewRecord
{
    protected static string $resource = FcOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isEditable()),
        ];
    }
}
