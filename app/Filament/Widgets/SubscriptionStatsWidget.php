<?php

namespace App\Filament\Widgets;

use App\Models\CustomerSubscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class SubscriptionStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';

    protected static ?int $sort = 50;

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

    protected function getStats(): array
    {
        $query = CustomerSubscription::query();

        // デバッグログ
        \Log::info('📊 SubscriptionStatsWidget - getStats()', [
            'selectedStoreId' => $this->selectedStoreId,
            'user_store_id' => auth()->user()?->store_id,
            'user_role' => auth()->user()?->getRoleNames()->first()
        ]);

        // 店舗フィルタリング（store_idで直接フィルタリング）
        if ($this->selectedStoreId) {
            $query->where('store_id', $this->selectedStoreId);
        }

        $activeCount = (clone $query)->where('status', 'active')->count();
        $expiringCount = (clone $query)->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', now())
            ->whereDate('end_date', '<=', now()->addDays(30))
            ->count();
        $monthlyRevenue = (clone $query)->where('status', 'active')
            ->sum('monthly_price');

        \Log::info('📊 SubscriptionStatsWidget - 計算結果', [
            'activeCount' => $activeCount,
            'expiringCount' => $expiringCount,
            'monthlyRevenue' => $monthlyRevenue
        ]);
        
        return [
            Stat::make('有効な契約数', $activeCount)
                ->description('アクティブなサブスク契約')
                ->descriptionIcon('heroicon-m-users')
                ->color('success')
                ->chart([7, 8, 9, 8, 10, 12, $activeCount]),
            
            Stat::make('期限切れ間近', $expiringCount)
                ->description('30日以内に期限切れ')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($expiringCount > 0 ? 'warning' : 'gray'),
            
            Stat::make('月間収益', '¥' . number_format($monthlyRevenue))
                ->description('サブスク月額合計')
                ->descriptionIcon('heroicon-m-currency-yen')
                ->color('primary'),
        ];
    }
}