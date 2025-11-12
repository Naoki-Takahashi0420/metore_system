<?php

namespace App\Livewire\Marketing;

use Livewire\Component;
use App\Services\MarketingAnalyticsService;

class CompleteFunnelStats extends Component
{
    public array $funnelData = [];
    public string $period = 'month';
    public ?int $store_id = null;
    public ?string $startDate = null;
    public ?string $endDate = null;
    public string $view = 'store'; // 'store' or 'staff'

    public function mount(string $period = 'month', ?int $store_id = null, ?string $startDate = null, ?string $endDate = null): void
    {
        $this->period = $period;
        $this->store_id = $store_id;
        $this->startDate = $startDate;
        $this->endDate = $endDate;

        $this->loadData();
    }

    protected $listeners = ['filtersUpdated' => 'updateData'];

    public function updateData($filters): void
    {
        $this->period = $filters['period'] ?? 'month';
        $this->store_id = $filters['store_id'] ?? null;
        $this->startDate = $filters['startDateA'] ?? null;
        $this->endDate = $filters['endDateA'] ?? null;

        $this->loadData();
    }

    public function loadData(): void
    {
        $service = new MarketingAnalyticsService();
        $this->funnelData = $service->getCompleteConversionFunnel(
            $this->period,
            $this->store_id,
            $this->startDate,
            $this->endDate
        );
    }

    public function switchView(string $view): void
    {
        $this->view = $view;
    }

    public function render()
    {
        return view('livewire.marketing.complete-funnel-stats');
    }
}
