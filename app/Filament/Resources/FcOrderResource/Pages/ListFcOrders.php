<?php

namespace App\Filament\Resources\FcOrderResource\Pages;

use App\Filament\Resources\FcOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFcOrders extends ListRecords
{
    protected static string $resource = FcOrderResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];

        // FC加盟店の場合は「商品カタログ」ボタンを表示
        $user = auth()->user();
        if ($user && $user->store && $user->store->isFcStore()) {
            $actions[] = Actions\Action::make('catalog')
                ->label('商品カタログから発注')
                ->icon('heroicon-o-shopping-bag')
                ->color('success')
                ->url(FcOrderResource::getUrl('catalog'));
        }

        // 本部の場合は「新規発注」ボタンを表示
        if ($user && ($user->hasRole('super_admin') || ($user->store && $user->store->isHeadquarters()))) {
            $actions[] = Actions\CreateAction::make()
                ->label('新規発注');
        }

        return $actions;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\FcStatsOverviewWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Widgets\FcUnshippedOrdersWidget::class,
        ];
    }
}
