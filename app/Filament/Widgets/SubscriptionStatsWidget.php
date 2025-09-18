<?php

namespace App\Filament\Widgets;

use App\Models\CustomerSubscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubscriptionStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    
    protected static ?int $sort = 50;

    protected function getStats(): array
    {
        $activeCount = CustomerSubscription::where('status', 'active')->count();
        $expiringCount = CustomerSubscription::where('status', 'active')
            ->whereDate('end_date', '<=', now()->addDays(30))
            ->count();
        $monthlyRevenue = CustomerSubscription::where('status', 'active')
            ->sum('monthly_price');
        
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