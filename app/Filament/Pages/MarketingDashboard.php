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

    public function mount(): void
    {
        // デフォルトは今月（パフォーマンス考慮・サーバークラッシュ防止）
        $this->startDateA = now()->startOfMonth()->format('Y-m-d');
        $this->endDateA = now()->format('Y-m-d');
    }

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

    public function setToday(): void
    {
        $this->startDateA = now()->format('Y-m-d');
        $this->endDateA = now()->format('Y-m-d');
        $this->period = 'custom';
        $this->refreshData();
    }

    public function setThisMonth(): void
    {
        $this->startDateA = now()->startOfMonth()->format('Y-m-d');
        $this->endDateA = now()->format('Y-m-d');
        $this->period = 'custom';
        $this->refreshData();
    }

    public function setLastMonth(): void
    {
        $this->startDateA = now()->subMonth()->startOfMonth()->format('Y-m-d');
        $this->endDateA = now()->subMonth()->endOfMonth()->format('Y-m-d');
        $this->period = 'custom';
        $this->refreshData();
    }

    public function setLast30Days(): void
    {
        $this->startDateA = now()->subDays(30)->format('Y-m-d');
        $this->endDateA = now()->format('Y-m-d');
        $this->period = 'custom';
        $this->refreshData();
    }

    public function setLast6Months(): void
    {
        $this->startDateA = now()->subMonths(6)->format('Y-m-d');
        $this->endDateA = now()->format('Y-m-d');
        $this->period = 'custom';
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