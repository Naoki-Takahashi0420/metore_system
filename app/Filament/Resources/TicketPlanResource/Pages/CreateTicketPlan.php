<?php

namespace App\Filament\Resources\TicketPlanResource\Pages;

use App\Filament\Resources\TicketPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicketPlan extends CreateRecord
{
    protected static string $resource = TicketPlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 有効期限を6ヶ月に固定
        $data['validity_months'] = 6;
        $data['validity_days'] = null;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
