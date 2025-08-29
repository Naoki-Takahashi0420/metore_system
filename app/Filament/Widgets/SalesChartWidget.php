<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class SalesChartWidget extends ChartWidget
{
    protected static ?string $heading = '売上推移';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';
    
    public ?string $filter = 'week';
    
    protected function getData(): array
    {
        $data = $this->getSalesData();
        
        return [
            'datasets' => [
                [
                    'label' => '売上高',
                    'data' => $data['values'],
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.3,
                ],
                [
                    'label' => '予約数',
                    'data' => $data['counts'],
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'tension' => 0.3,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $data['labels'],
        ];
    }
    
    protected function getType(): string
    {
        return 'line';
    }
    
    protected function getFilters(): ?array
    {
        return [
            'week' => '過去7日間',
            'month' => '過去30日間',
            'quarter' => '過去3ヶ月',
            'year' => '過去1年',
        ];
    }
    
    private function getSalesData(): array
    {
        $labels = [];
        $values = [];
        $counts = [];
        
        switch ($this->filter) {
            case 'week':
                for ($i = 6; $i >= 0; $i--) {
                    $date = Carbon::today()->subDays($i);
                    $labels[] = $date->format('m/d');
                    
                    $dayData = Reservation::whereDate('reservation_date', $date)
                        ->where('status', 'completed')
                        ->selectRaw('SUM(total_amount) as total, COUNT(*) as count')
                        ->first();
                    
                    $values[] = $dayData->total ?? 0;
                    $counts[] = $dayData->count ?? 0;
                }
                break;
                
            case 'month':
                for ($i = 29; $i >= 0; $i--) {
                    $date = Carbon::today()->subDays($i);
                    $labels[] = $date->format('m/d');
                    
                    $dayData = Reservation::whereDate('reservation_date', $date)
                        ->where('status', 'completed')
                        ->selectRaw('SUM(total_amount) as total, COUNT(*) as count')
                        ->first();
                    
                    $values[] = $dayData->total ?? 0;
                    $counts[] = $dayData->count ?? 0;
                }
                break;
                
            case 'quarter':
                for ($i = 11; $i >= 0; $i--) {
                    $startOfWeek = Carbon::now()->subWeeks($i)->startOfWeek();
                    $endOfWeek = Carbon::now()->subWeeks($i)->endOfWeek();
                    $labels[] = $startOfWeek->format('m/d');
                    
                    $weekData = Reservation::whereBetween('reservation_date', [$startOfWeek, $endOfWeek])
                        ->where('status', 'completed')
                        ->selectRaw('SUM(total_amount) as total, COUNT(*) as count')
                        ->first();
                    
                    $values[] = $weekData->total ?? 0;
                    $counts[] = $weekData->count ?? 0;
                }
                break;
                
            case 'year':
                for ($i = 11; $i >= 0; $i--) {
                    $month = Carbon::now()->subMonths($i);
                    $labels[] = $month->format('Y/m');
                    
                    $monthData = Reservation::whereYear('reservation_date', $month->year)
                        ->whereMonth('reservation_date', $month->month)
                        ->where('status', 'completed')
                        ->selectRaw('SUM(total_amount) as total, COUNT(*) as count')
                        ->first();
                    
                    $values[] = $monthData->total ?? 0;
                    $counts[] = $monthData->count ?? 0;
                }
                break;
        }
        
        return [
            'labels' => $labels,
            'values' => $values,
            'counts' => $counts,
        ];
    }
}