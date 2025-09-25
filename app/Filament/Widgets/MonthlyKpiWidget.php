<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Services\MarketingAnalyticsService;

class MonthlyKpiWidget extends Widget
{
    protected static string $view = 'filament.widgets.monthly-kpi';
    protected int | string | array $columnSpan = 'full';

    public array $kpiData = [];

    public function mount(): void
    {
        $service = new MarketingAnalyticsService();

        // フィルターから期間と店舗を取得（ページのフィルターと連動）
        $period = request()->get('period', 'month');
        $storeId = request()->get('store_id');

        $this->kpiData = $service->getMonthlyKpis($period, $storeId);
    }

    protected function getViewData(): array
    {
        return [
            'kpiData' => $this->kpiData,
        ];
    }
}