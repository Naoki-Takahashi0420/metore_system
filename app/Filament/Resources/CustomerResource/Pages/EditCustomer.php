<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerSubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected static bool $shouldCheckUnsavedChanges = true;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()->label('表示'),
            Actions\Action::make('add_subscription')
                ->label('サブスク契約を追加')
                ->color('success')
                ->icon('heroicon-o-plus-circle')
                ->url(fn () => CustomerSubscriptionResource::getUrl('create', [
                    'customer_id' => $this->record->id,
                ])),
            Actions\DeleteAction::make()->label('削除'),
        ];
    }
}
