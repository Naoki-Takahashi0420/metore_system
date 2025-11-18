<?php

namespace App\Filament\Resources\FcOrderResource\Pages;

use App\Filament\Resources\FcOrderResource;
use App\Models\FcOrder;
use Filament\Resources\Pages\CreateRecord;

class CreateFcOrder extends CreateRecord
{
    protected static string $resource = FcOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['order_number'] = FcOrder::generateOrderNumber();
        $data['status'] = 'draft';

        return $data;
    }

    protected function afterCreate(): void
    {
        // 合計金額を再計算
        $this->record->recalculateTotals();
    }
}
