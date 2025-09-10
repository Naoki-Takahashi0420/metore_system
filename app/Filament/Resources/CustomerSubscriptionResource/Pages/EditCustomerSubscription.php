<?php

namespace App\Filament\Resources\CustomerSubscriptionResource\Pages;

use App\Filament\Resources\CustomerSubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerSubscription extends EditRecord
{
    protected static string $resource = CustomerSubscriptionResource::class;
    
    protected static ?string $title = 'サブスク契約詳細';
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_customer')
                ->label('顧客管理へ戻る')
                ->url(fn ($record) => route('filament.admin.resources.customers.edit', $record->customer_id))
                ->icon('heroicon-o-arrow-left'),
        ];
    }
}
