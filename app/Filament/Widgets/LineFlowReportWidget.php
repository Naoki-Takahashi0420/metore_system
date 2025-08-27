<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Store;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class LineFlowReportWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('LINE登録者総数', $this->getTotalLineUsers())
                ->description('全期間の累計')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('success'),

            Stat::make('今月の新規LINE登録', $this->getThisMonthLineUsers())
                ->description($this->getThisMonthGrowth() . ' 前月比')
                ->descriptionIcon($this->getThisMonthGrowth() >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($this->getThisMonthGrowth() >= 0 ? 'success' : 'danger'),

            Stat::make('今週の新規LINE登録', $this->getThisWeekLineUsers())
                ->description($this->getThisWeekGrowth() . ' 前週比')
                ->descriptionIcon($this->getThisWeekGrowth() >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($this->getThisWeekGrowth() >= 0 ? 'success' : 'danger'),

            Stat::make('アクティブ通知ユーザー', $this->getActiveNotificationUsers())
                ->description('通知有効なLINEユーザー')
                ->descriptionIcon('heroicon-m-bell')
                ->color('info'),
        ];
    }

    protected function getTotalLineUsers(): string
    {
        $count = Customer::whereNotNull('line_user_id')
                        ->whereNotNull('line_registered_at')
                        ->count();
        
        return number_format($count) . '人';
    }

    protected function getThisMonthLineUsers(): string
    {
        $count = Customer::whereNotNull('line_user_id')
                        ->whereNotNull('line_registered_at')
                        ->whereBetween('line_registered_at', [
                            Carbon::now()->startOfMonth(),
                            Carbon::now()->endOfMonth()
                        ])
                        ->count();
        
        return number_format($count) . '人';
    }

    protected function getThisWeekLineUsers(): string
    {
        $count = Customer::whereNotNull('line_user_id')
                        ->whereNotNull('line_registered_at')
                        ->whereBetween('line_registered_at', [
                            Carbon::now()->startOfWeek(),
                            Carbon::now()->endOfWeek()
                        ])
                        ->count();
        
        return number_format($count) . '人';
    }

    protected function getActiveNotificationUsers(): string
    {
        $count = Customer::whereNotNull('line_user_id')
                        ->where('line_notifications_enabled', true)
                        ->count();
        
        return number_format($count) . '人';
    }

    protected function getThisMonthGrowth(): float
    {
        $thisMonth = Customer::whereNotNull('line_user_id')
                           ->whereBetween('line_registered_at', [
                               Carbon::now()->startOfMonth(),
                               Carbon::now()->endOfMonth()
                           ])
                           ->count();

        $lastMonth = Customer::whereNotNull('line_user_id')
                           ->whereBetween('line_registered_at', [
                               Carbon::now()->subMonth()->startOfMonth(),
                               Carbon::now()->subMonth()->endOfMonth()
                           ])
                           ->count();

        if ($lastMonth === 0) {
            return $thisMonth > 0 ? 100 : 0;
        }

        return round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1);
    }

    protected function getThisWeekGrowth(): float
    {
        $thisWeek = Customer::whereNotNull('line_user_id')
                          ->whereBetween('line_registered_at', [
                              Carbon::now()->startOfWeek(),
                              Carbon::now()->endOfWeek()
                          ])
                          ->count();

        $lastWeek = Customer::whereNotNull('line_user_id')
                          ->whereBetween('line_registered_at', [
                              Carbon::now()->subWeek()->startOfWeek(),
                              Carbon::now()->subWeek()->endOfWeek()
                          ])
                          ->count();

        if ($lastWeek === 0) {
            return $thisWeek > 0 ? 100 : 0;
        }

        return round((($thisWeek - $lastWeek) / $lastWeek) * 100, 1);
    }

    protected function getColumns(): int
    {
        return 4;
    }
}