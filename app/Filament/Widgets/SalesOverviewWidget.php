<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use App\Models\Reservation;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class SalesOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();
        
        // 今日の売上
        $todaySales = Sale::whereDate('sale_date', $today)
            ->where('status', 'completed')
            ->sum('total_amount');
            
        // 昨日の売上
        $yesterdaySales = Sale::whereDate('sale_date', $yesterday)
            ->where('status', 'completed')
            ->sum('total_amount');
            
        // 今月の売上
        $thisMonthSales = Sale::whereBetween('sale_date', [$thisMonth, $today])
            ->where('status', 'completed')
            ->sum('total_amount');
            
        // 先月の売上
        $lastMonthSales = Sale::whereBetween('sale_date', [$lastMonth, $lastMonthEnd])
            ->where('status', 'completed')
            ->sum('total_amount');
            
        // 今日の客数
        $todayCustomers = Sale::whereDate('sale_date', $today)
            ->where('status', 'completed')
            ->distinct('customer_id')
            ->count('customer_id');
            
        // 今日の予約数
        $todayReservations = Reservation::whereDate('reservation_date', $today)
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();
        
        // 前日比計算
        $dailyChange = $yesterdaySales > 0 
            ? round((($todaySales - $yesterdaySales) / $yesterdaySales) * 100, 1)
            : 0;
            
        // 前月比計算
        $monthlyChange = $lastMonthSales > 0
            ? round((($thisMonthSales - $lastMonthSales) / $lastMonthSales) * 100, 1)
            : 0;
            
        // 客単価
        $averagePrice = $todayCustomers > 0 
            ? round($todaySales / $todayCustomers)
            : 0;

        return [
            Stat::make('本日の売上', '¥' . number_format($todaySales))
                ->description(
                    $dailyChange >= 0 
                        ? "前日比 +{$dailyChange}%" 
                        : "前日比 {$dailyChange}%"
                )
                ->descriptionIcon(
                    $dailyChange >= 0 
                        ? 'heroicon-m-arrow-trending-up' 
                        : 'heroicon-m-arrow-trending-down'
                )
                ->color($dailyChange >= 0 ? 'success' : 'danger')
                ->chart($this->getLastWeekSales()),
                
            Stat::make('今月の売上', '¥' . number_format($thisMonthSales))
                ->description(
                    $monthlyChange >= 0 
                        ? "前月比 +{$monthlyChange}%" 
                        : "前月比 {$monthlyChange}%"
                )
                ->descriptionIcon(
                    $monthlyChange >= 0 
                        ? 'heroicon-m-arrow-trending-up' 
                        : 'heroicon-m-arrow-trending-down'
                )
                ->color($monthlyChange >= 0 ? 'success' : 'danger')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition',
                    'onclick' => "window.location.href='/admin/sales'",
                    'title' => '売上管理へ移動',
                ]),
                
            Stat::make('本日の来店客数', $todayCustomers . '名')
                ->description('客単価: ¥' . number_format($averagePrice))
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
                
            Stat::make('本日の予約', $todayReservations . '件')
                ->description('確認待ち・確定済み')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition',
                    'onclick' => "window.location.href='/admin/reservations'",
                    'title' => '予約一覧へ移動',
                ]),
        ];
    }
    
    protected function getLastWeekSales(): array
    {
        $sales = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $sales[] = Sale::whereDate('sale_date', $date)
                ->where('status', 'completed')
                ->sum('total_amount');
        }
        return $sales;
    }
}