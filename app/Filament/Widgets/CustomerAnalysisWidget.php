<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Services\MarketingAnalyticsService;

class CustomerAnalysisWidget extends Widget
{
    protected static string $view = 'filament.widgets.customer-analysis';
    protected int | string | array $columnSpan = 'full';

    public array $customerData = [];

    public function mount(): void
    {
        $service = new MarketingAnalyticsService();

        // フィルターから期間と店舗を取得
        $period = request()->get('period', 'month');
        $storeId = request()->get('store_id');

        $this->customerData = $service->getCustomerAnalysis($period, $storeId);
    }

    protected function getViewData(): array
    {
        return [
            'customerData' => $this->customerData,
        ];
    }
}