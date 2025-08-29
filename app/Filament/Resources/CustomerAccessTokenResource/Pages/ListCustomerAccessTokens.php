<?php

namespace App\Filament\Resources\CustomerAccessTokenResource\Pages;

use App\Filament\Resources\CustomerAccessTokenResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerAccessTokens extends ListRecords
{
    protected static string $resource = CustomerAccessTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('新規作成'),
            Actions\Action::make('generate')
                ->label('トークン生成')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->url(fn () => static::getResource()::getUrl('generate')),
        ];
    }
}
