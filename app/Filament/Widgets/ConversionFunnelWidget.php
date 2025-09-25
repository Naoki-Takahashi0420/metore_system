<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Services\MarketingAnalyticsService;

class ConversionFunnelWidget extends Widget
{
    protected static string $view = 'filament.widgets.conversion-funnel';
    protected int | string | array $columnSpan = 'full';

    public array $funnelData = [];

    public function mount(): void
    {
        $service = new MarketingAnalyticsService();

        // フィルターから期間と店舗を取得
        $period = request()->get('period', 'month');
        $storeId = request()->get('store_id');

        $this->funnelData = $service->getConversionFunnel($period, $storeId);
    }

    protected function getViewData(): array
    {
        return [
            'funnelData' => $this->funnelData,
        ];
    }
}