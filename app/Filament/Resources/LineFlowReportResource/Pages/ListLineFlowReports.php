<?php

namespace App\Filament\Resources\LineFlowReportResource\Pages;

use App\Filament\Resources\LineFlowReportResource;
use App\Filament\Widgets\LineFlowReportWidget;
use App\Filament\Widgets\LineStoreFlowWidget;
use Filament\Resources\Pages\ListRecords;

class ListLineFlowReports extends ListRecords
{
    protected static string $resource = LineFlowReportResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            LineFlowReportWidget::class,
            LineStoreFlowWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // アクションは不要（レポート専用画面のため）
        ];
    }
}