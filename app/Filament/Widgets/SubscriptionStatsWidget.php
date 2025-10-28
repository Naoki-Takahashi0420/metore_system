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

        // デバッグ：全件数確認
        $totalCount = CustomerSubscription::count();
        $totalActiveCount = CustomerSubscription::where('status', 'active')->count();

        // 店舗フィルタリング
        if ($this->selectedStoreId) {
            $query->where('store_id', $this->selectedStoreId);
        }

        // 今月のアクティブな契約の条件：
        // status = 'active' のみ（シンプルに）
        $baseQuery = (clone $query)->where('status', 'active');

        // SQL確認用
        $sql = $baseQuery->toSql();
        $bindings = $baseQuery->getBindings();

        // 有効な契約数（今月時点でアクティブ）
        $activeCount = (clone $baseQuery)->count();

        // 期限切れ間近（30日以内に期限切れ）
        $expiringCount = (clone $baseQuery)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', now())
            ->whereDate('end_date', '<=', now()->addDays(30))
            ->count();

        // 月間収益（今月時点でアクティブな契約の月額合計）
        $monthlyRevenue = (clone $baseQuery)->sum('monthly_price');

        // デバッグ：店舗別の件数確認
        $storeBreakdown = CustomerSubscription::selectRaw('store_id, COUNT(*) as count, SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_count')
            ->groupBy('store_id')
            ->get()
            ->map(function($item) {
                return [
                    'store_id' => $item->store_id,
                    'total' => $item->count,
                    'active' => $item->active_count
                ];
            })
            ->toArray();

        // 詳細デバッグログ
        \Log::info('📊 SubscriptionStatsWidget - 詳細デバッグ', [
            'selectedStoreId' => $this->selectedStoreId,
            'totalCount' => $totalCount,
            'totalActiveCount' => $totalActiveCount,
            'storeBreakdown' => $storeBreakdown,
            'sql' => $sql,
            'bindings' => $bindings,
            'activeCount' => $activeCount,
            'expiringCount' => $expiringCount,
            'monthlyRevenue' => $monthlyRevenue,
            'today' => now()->toDateString(),
        ]);

        return [
            Stat::make('今月の有効契約数', $activeCount)
                ->description('アクティブなサブスク契約')
                ->descriptionIcon('heroicon-m-users')
                ->color('success')
                ->chart([7, 8, 9, 8, 10, 12, $activeCount]),

            Stat::make('期限切れ間近', $expiringCount)
                ->description('30日以内に期限切れ')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($expiringCount > 0 ? 'warning' : 'gray'),

            Stat::make('今月の収益見込', '¥' . number_format($monthlyRevenue))
                ->description('サブスク月額合計')
                ->descriptionIcon('heroicon-m-currency-yen')
                ->color('primary'),
        ];
    }
}