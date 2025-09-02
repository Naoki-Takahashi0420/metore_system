<?php

namespace App\Filament\Resources\ReservationLineResource\Pages;

use App\Filament\Resources\ReservationLineResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReservationLines extends ListRecords
{
    protected static string $resource = ReservationLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            
            Actions\Action::make('initialize_lines')
                ->label('店舗ラインを初期化')
                ->icon('heroicon-o-cog')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('店舗のラインを初期化')
                ->modalDescription('選択した店舗の設定に基づいてラインを自動作成します。既存のラインは削除されません。')
                ->form([
                    \Filament\Forms\Components\Select::make('store_id')
                        ->label('店舗を選択')
                        ->options(\App\Models\Store::pluck('name', 'id'))
                        ->required(),
                ])
                ->action(function (array $data) {
                    $store = \App\Models\Store::find($data['store_id']);
                    app(\App\Services\ReservationLineService::class)->initializeStoreLines($store);
                    
                    $this->notify('success', "{$store->name}のラインを初期化しました");
                }),
        ];
    }
}