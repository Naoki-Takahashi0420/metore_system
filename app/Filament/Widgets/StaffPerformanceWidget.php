<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Services\MarketingAnalyticsService;

class StaffPerformanceWidget extends Widget
{
    protected static string $view = 'filament.widgets.staff-performance';
    protected int | string | array $columnSpan = 'full';

    public array $staffData = [];

    public function mount(): void
    {
        $service = new MarketingAnalyticsService();

        // フィルターから期間と店舗を取得
        $period = request()->get('period', 'month');
        $storeId = request()->get('store_id');

        $this->staffData = $service->getStaffPerformance($period, $storeId);
    }

    protected function getViewData(): array
    {
        return [
            'staffData' => $this->staffData,
        ];
    }
}