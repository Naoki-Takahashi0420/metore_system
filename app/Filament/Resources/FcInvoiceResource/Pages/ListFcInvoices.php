<?php

namespace App\Filament\Resources\FcInvoiceResource\Pages;

use App\Filament\Resources\FcInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFcInvoices extends ListRecords
{
    protected static string $resource = FcInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('新規請求書'),
        ];
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
            \App\Filament\Widgets\FcUnpaidInvoicesWidget::class,
        ];
    }
}
