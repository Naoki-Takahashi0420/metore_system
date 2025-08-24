<?php

namespace App\Filament\Resources\ShiftResource\Widgets;

use App\Models\Shift;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class ShiftStats extends BaseWidget
{
    protected function getStats(): array
    {
        $today = Carbon::today();
        $thisWeek = [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek(),
        ];
        $thisMonth = [
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        ];
        
        return [
            Stat::make('今日のシフト', Shift::today()->count() . '名')
                ->description('本日勤務予定のスタッフ数')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),
            
            Stat::make('今週のシフト', Shift::thisWeek()->count() . '件')
                ->description('今週のシフト総数')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('success'),
            
            Stat::make('今月の総労働時間', $this->getMonthlyHours() . '時間')
                ->description('今月の全スタッフ合計')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            
            Stat::make('現在勤務中', $this->getCurrentlyWorking() . '名')
                ->description('現在勤務中のスタッフ')
                ->descriptionIcon('heroicon-m-signal')
                ->color('info'),
        ];
    }
    
    private function getMonthlyHours(): float
    {
        $shifts = Shift::forMonth(now()->year, now()->month)
            ->where('status', '!=', 'cancelled')
            ->get();
        
        $totalHours = 0;
        foreach ($shifts as $shift) {
            $totalHours += $shift->working_hours;
        }
        
        return round($totalHours, 1);
    }
    
    private function getCurrentlyWorking(): int
    {
        return Shift::today()
            ->where('status', 'working')
            ->count();
    }
}