<?php

namespace App\Filament\Resources\TicketPlanResource\Pages;

use App\Filament\Resources\TicketPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTicketPlan extends EditRecord
{
    protected static string $resource = TicketPlanResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // 有効期限を6ヶ月に固定（編集時も変更不可）
        $data['validity_months'] = 6;
        $data['validity_days'] = null;

        return $data;
    }

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
