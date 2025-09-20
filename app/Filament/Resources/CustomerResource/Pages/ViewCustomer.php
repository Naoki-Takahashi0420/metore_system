<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerSubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('編集'),
            Actions\Action::make('add_subscription')
                ->label('サブスク契約を追加')
                ->color('success')
                ->icon('heroicon-o-plus-circle')
                ->url(fn () => CustomerSubscriptionResource::getUrl('create', [
                    'customer_id' => $this->record->id,
                ])),
        ];
    }
}