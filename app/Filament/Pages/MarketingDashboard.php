<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class MarketingDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'マーケティング分析';
    protected static ?string $navigationGroup = '分析・レポート';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.marketing-dashboard';

    public string $period = 'month';
    public ?int $store_id = null;
    public bool $compareMode = false;
    public ?string $startDateA = null;
    public ?string $endDateA = null;
    public ?string $startDateB = null;
    public ?string $endDateB = null;

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['staff', 'manager', 'owner', 'super_admin']);
    }

    public function updatedPeriod(): void
    {
        $this->refreshData();
    }

    public function updatedStoreId(): void
    {
        $this->refreshData();
    }

    public function updatedCompareMode(): void
    {
        $this->refreshData();
    }

    public function updatedStartDateA(): void
    {
        $this->refreshData();
    }

    public function updatedEndDateA(): void
    {
        $this->refreshData();
    }

    public function updatedStartDateB(): void
    {
        $this->refreshData();
    }

    public function updatedEndDateB(): void
    {
        $this->refreshData();
    }

    private function refreshData(): void
    {
        $this->dispatch('filtersUpdated', [
            'period' => $this->period,
            'store_id' => $this->store_id,
            'compareMode' => $this->compareMode,
            'startDateA' => $this->startDateA,
            'endDateA' => $this->endDateA,
            'startDateB' => $this->startDateB,
            'endDateB' => $this->endDateB,
        ]);
    }
}