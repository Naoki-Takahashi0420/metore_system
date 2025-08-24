<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TopMenusWidget extends Widget
{
    protected static ?int $sort = 3;
    
    protected static string $view = 'filament.widgets.top-menus-widget';
    
    protected int $pollInterval = 60; // 60秒ごとに更新
    
    public function getTopMenus()
    {
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();
        
        return DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->where('sales.status', 'completed')
            ->select(
                'sale_items.item_name',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(sale_items.quantity) as quantity'),
                DB::raw('SUM(sale_items.amount) as total_amount')
            )
            ->groupBy('sale_items.item_name')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();
    }
    
    public function getTimeRangeSales()
    {
        $today = Carbon::today();
        
        $timeRanges = [
            '09:00-12:00' => ['09:00:00', '12:00:00'],
            '12:00-15:00' => ['12:00:00', '15:00:00'],
            '15:00-18:00' => ['15:00:00', '18:00:00'],
            '18:00-21:00' => ['18:00:00', '21:00:00'],
        ];
        
        $result = [];
        
        foreach ($timeRanges as $label => $times) {
            $sales = Sale::whereDate('sale_date', $today)
                ->whereTime('sale_time', '>=', $times[0])
                ->whereTime('sale_time', '<', $times[1])
                ->where('status', 'completed')
                ->sum('total_amount');
                
            $result[] = [
                'label' => $label,
                'amount' => $sales,
            ];
        }
        
        return $result;
    }
}