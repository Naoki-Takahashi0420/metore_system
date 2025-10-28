<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentSource;
use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;
use Livewire\Attributes\On;

class PaymentSourceCountersWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected static ?string $pollingInterval = '60s';

    public ?int $selectedStoreId = null;

    public function mount(): void
    {
        $user = auth()->user();

        // 初期店舗設定
        if ($user->hasRole('super_admin')) {
            $firstStore = \App\Models\Store::first();
            $this->selectedStoreId = $firstStore?->id;
        } else {
            $this->selectedStoreId = $user->store_id;
        }
    }

    #[On('store-changed')]
    public function updateStore($storeId, $date = null): void
    {
        $this->selectedStoreId = $storeId;
    }

    public static function canView(): bool
    {
        $user = auth()->user();
        return $user && !$user->hasRole('staff');
    }

    protected function getStats(): array
    {
        $stats = [];
        $today = Carbon::today();

        // スポット件数
        $spotQuery = Sale::where('payment_source', PaymentSource::SPOT->value)
            ->where('status', 'completed')
            ->whereDate('sale_date', $today);
        if ($this->selectedStoreId) {
            $spotQuery->where('store_id', $this->selectedStoreId);
        }
        $spotCount = $spotQuery->count();
        $spotAmount = (int) $spotQuery->sum('total_amount');

        $stats[] = Stat::make('スポット利用', $spotCount . '件')
            ->description('金額: ¥' . number_format($spotAmount))
            ->descriptionIcon('heroicon-m-currency-yen')
            ->color('gray');

        // サブスク利用件数
        $subQuery = Sale::where('payment_source', PaymentSource::SUBSCRIPTION->value)
            ->where('status', 'completed')
            ->whereDate('sale_date', $today);
        if ($this->selectedStoreId) {
            $subQuery->where('store_id', $this->selectedStoreId);
        }
        $subCount = $subQuery->count();
        $subAmount = (int) $subQuery->sum('total_amount');

        $stats[] = Stat::make('サブスク利用', $subCount . '件')
            ->description($subAmount > 0 ? '物販: ¥' . number_format($subAmount) : '基本料金: 0円')
            ->descriptionIcon('heroicon-m-arrow-path')
            ->color('info');

        // 回数券利用件数
        $ticketQuery = Sale::where('payment_source', PaymentSource::TICKET->value)
            ->where('status', 'completed')
            ->whereDate('sale_date', $today);
        if ($this->selectedStoreId) {
            $ticketQuery->where('store_id', $this->selectedStoreId);
        }
        $ticketCount = $ticketQuery->count();
        $ticketAmount = (int) $ticketQuery->sum('total_amount');

        $stats[] = Stat::make('回数券利用', $ticketCount . '件')
            ->description($ticketAmount > 0 ? '物販: ¥' . number_format($ticketAmount) : '基本料金: 0円')
            ->descriptionIcon('heroicon-m-ticket')
            ->color('success');

        // 合計件数
        $totalCount = $spotCount + $subCount + $ticketCount;
        $stats[] = Stat::make('合計件数', $totalCount . '件')
            ->description('本日の件数')
            ->descriptionIcon('heroicon-m-check-badge')
            ->color('primary')
            ->extraAttributes(['class' => 'col-span-full border-2 border-blue-500']);

        return $stats;
    }
}