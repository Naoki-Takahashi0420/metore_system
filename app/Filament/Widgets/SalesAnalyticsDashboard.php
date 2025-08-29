<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use App\Models\Store;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class SalesAnalyticsDashboard extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected function getStats(): array
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();
        
        // 今日の売上
        $todaySales = Reservation::whereDate('reservation_date', $today)
            ->where('status', 'completed')
            ->sum('total_amount');
        
        // 昨日の売上
        $yesterdaySales = Reservation::whereDate('reservation_date', $today->copy()->subDay())
            ->where('status', 'completed')
            ->sum('total_amount');
        
        // 今月の売上
        $thisMonthSales = Reservation::whereBetween('reservation_date', [$thisMonth, Carbon::now()])
            ->where('status', 'completed')
            ->sum('total_amount');
        
        // 先月の売上
        $lastMonthSales = Reservation::whereBetween('reservation_date', [$lastMonth, $lastMonthEnd])
            ->where('status', 'completed')
            ->sum('total_amount');
        
        // 今日の予約数
        $todayReservations = Reservation::whereDate('reservation_date', $today)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->count();
        
        // 今月の予約数
        $thisMonthReservations = Reservation::whereBetween('reservation_date', [$thisMonth, Carbon::now()])
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->count();
        
        // 平均客単価
        $averagePrice = $thisMonthSales > 0 && $thisMonthReservations > 0
            ? $thisMonthSales / $thisMonthReservations
            : 0;
        
        // キャンセル率
        $totalReservations = Reservation::whereBetween('reservation_date', [$thisMonth, Carbon::now()])
            ->count();
        $cancelledReservations = Reservation::whereBetween('reservation_date', [$thisMonth, Carbon::now()])
            ->whereIn('status', ['cancelled', 'canceled'])
            ->count();
        $cancelRate = $totalReservations > 0
            ? ($cancelledReservations / $totalReservations) * 100
            : 0;
        
        // 売上の増減計算
        $salesChange = $yesterdaySales > 0
            ? (($todaySales - $yesterdaySales) / $yesterdaySales) * 100
            : 0;
        
        $monthlyChange = $lastMonthSales > 0
            ? (($thisMonthSales - $lastMonthSales) / $lastMonthSales) * 100
            : 0;
        
        return [
            Stat::make('本日の売上', '¥' . Number::format($todaySales))
                ->description($salesChange >= 0 
                    ? '前日比 +' . number_format(abs($salesChange), 1) . '%'
                    : '前日比 ' . number_format($salesChange, 1) . '%')
                ->descriptionIcon($salesChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($salesChange >= 0 ? 'success' : 'danger')
                ->chart($this->getLastWeekSales()),
                
            Stat::make('今月の売上', '¥' . Number::format($thisMonthSales))
                ->description($monthlyChange >= 0
                    ? '前月比 +' . number_format(abs($monthlyChange), 1) . '%'
                    : '前月比 ' . number_format($monthlyChange, 1) . '%')
                ->descriptionIcon($monthlyChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($monthlyChange >= 0 ? 'success' : 'danger')
                ->chart($this->getMonthlyTrend()),
                
            Stat::make('本日の予約', $todayReservations . '件')
                ->description('確定済み')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),
                
            Stat::make('平均客単価', '¥' . Number::format($averagePrice))
                ->description('今月の平均')
                ->descriptionIcon('heroicon-m-currency-yen')
                ->color('warning'),
                
            Stat::make('キャンセル率', number_format($cancelRate, 1) . '%')
                ->description('今月のキャンセル率')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($cancelRate > 10 ? 'danger' : 'success'),
                
            Stat::make('リピート率', $this->getRepeatRate() . '%')
                ->description('過去3ヶ月')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('primary'),
        ];
    }
    
    protected function getLastWeekSales(): array
    {
        $sales = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $sales[] = Reservation::whereDate('reservation_date', $date)
                ->where('status', 'completed')
                ->sum('total_amount');
        }
        return $sales;
    }
    
    protected function getMonthlyTrend(): array
    {
        $sales = [];
        for ($i = 5; $i >= 0; $i--) {
            $startOfMonth = Carbon::now()->subMonths($i)->startOfMonth();
            $endOfMonth = Carbon::now()->subMonths($i)->endOfMonth();
            $sales[] = Reservation::whereBetween('reservation_date', [$startOfMonth, $endOfMonth])
                ->where('status', 'completed')
                ->sum('total_amount');
        }
        return $sales;
    }
    
    protected function getRepeatRate(): float
    {
        $threeMonthsAgo = Carbon::now()->subMonths(3);
        
        // 3ヶ月以内に来店した顧客数
        $totalCustomers = Reservation::where('reservation_date', '>=', $threeMonthsAgo)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->distinct('customer_id')
            ->count('customer_id');
        
        // その中で2回以上来店した顧客数
        $repeatCustomers = Reservation::where('reservation_date', '>=', $threeMonthsAgo)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        
        return $totalCustomers > 0
            ? round(($repeatCustomers / $totalCustomers) * 100, 1)
            : 0;
    }
}