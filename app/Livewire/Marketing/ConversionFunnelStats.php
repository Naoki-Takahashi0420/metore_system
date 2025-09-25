<?php

namespace App\Livewire\Marketing;

use Livewire\Component;
use App\Services\MarketingAnalyticsService;

class ConversionFunnelStats extends Component
{
    public array $funnelData = [];
    public string $period = 'month';
    public ?int $store_id = null;
    public ?string $startDate = null;
    public ?string $endDate = null;

    public function mount(string $period = 'month', ?int $store_id = null, ?string $startDate = null, ?string $endDate = null): void
    {
        $this->period = $period;
        $this->store_id = $store_id;
        $this->startDate = $startDate;
        $this->endDate = $endDate;

        $service = new MarketingAnalyticsService();
        $this->funnelData = $service->getConversionFunnel($this->period, $this->store_id, $this->startDate, $this->endDate);
    }

    protected $listeners = ['filtersUpdated' => 'updateData'];

    public function updateData($filters): void
    {
        $this->period = $filters['period'] ?? 'month';
        $this->store_id = $filters['store_id'] ?? null;

        $service = new MarketingAnalyticsService();
        $this->funnelData = $service->getConversionFunnel($this->period, $this->store_id);
    }

    public function render()
    {
        return view('livewire.marketing.conversion-funnel-stats');
    }
}