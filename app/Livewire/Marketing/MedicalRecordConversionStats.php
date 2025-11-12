<?php

namespace App\Livewire\Marketing;

use Livewire\Component;
use App\Services\MarketingAnalyticsService;

class MedicalRecordConversionStats extends Component
{
    public array $conversionData = [];
    public string $period = 'month';
    public ?int $store_id = null;
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?int $handler_id = null;
    public array $expandedHandlers = [];
    public array $availableHandlers = [];

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
        $this->conversionData = $service->getMedicalRecordConversionAnalysis(
            $this->period,
            $this->store_id,
            $this->startDate,
            $this->endDate,
            $this->handler_id
        );

        // 対応者リストを取得
        $this->availableHandlers = \App\Models\User::query()
            ->whereIn('id', collect($this->conversionData)->pluck('handler_id'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function toggleHandler(int $handlerId): void
    {
        if (in_array($handlerId, $this->expandedHandlers)) {
            $this->expandedHandlers = array_diff($this->expandedHandlers, [$handlerId]);
        } else {
            $this->expandedHandlers[] = $handlerId;
        }
    }

    public function isExpanded(int $handlerId): bool
    {
        return in_array($handlerId, $this->expandedHandlers);
    }

    // handler_id が変更されたら自動的にデータを再読み込み
    public function updatedHandlerId(): void
    {
        $this->loadData();
    }

    public function render()
    {
        return view('livewire.marketing.medical-record-conversion-stats');
    }
}
