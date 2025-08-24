<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class SalesChartWidget extends ChartWidget
{
    protected static ?string $heading = '売上推移（過去30日）';
    
    protected static ?int $sort = 2;
    
    protected static ?string $maxHeight = '300px';
    
    protected static string $color = 'primary';
    
    protected function getData(): array
    {
        $sales = [];
        $labels = [];
        
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $labels[] = $date->format('m/d');
            $sales[] = Sale::whereDate('sale_date', $date)
                ->where('status', 'completed')
                ->sum('total_amount');
        }
        
        return [
            'datasets' => [
                [
                    'label' => '売上',
                    'data' => $sales,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
    
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) { return "¥" + value.toLocaleString(); }',
                    ],
                ],
            ],
        ];
    }
}