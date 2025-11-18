<?php

namespace App\Filament\Resources\FcOrderResource\Pages;

use App\Filament\Resources\FcOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFcOrder extends EditRecord
{
    protected static string $resource = FcOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->isEditable()),
        ];
    }

    protected function afterSave(): void
    {
        // 合計金額を再計算
        $this->record->recalculateTotals();
    }
}
