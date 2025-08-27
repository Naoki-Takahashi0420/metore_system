<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Store;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class LineStoreFlowWidget extends ChartWidget
{
    protected static ?string $heading = '店舗別LINE登録流入';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $period = $this->filter ?? 'month';
        
        $startDate = match($period) {
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'quarter' => Carbon::now()->startOfQuarter(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth(),
        };

        // 店舗別の登録数を取得
        $storeData = Store::with(['lineRegisteredCustomers' => function($query) use ($startDate) {
            $query->where('line_registered_at', '>=', $startDate);
        }])->get();

        $labels = [];
        $data = [];
        $backgroundColor = [];
        
        $colors = [
            'rgba(255, 99, 132, 0.8)',
            'rgba(54, 162, 235, 0.8)',
            'rgba(255, 205, 86, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(153, 102, 255, 0.8)',
            'rgba(255, 159, 64, 0.8)',
            'rgba(199, 199, 199, 0.8)',
            'rgba(83, 102, 255, 0.8)',
        ];

        foreach ($storeData as $index => $store) {
            $count = $store->lineRegisteredCustomers->count();
            if ($count > 0) {
                $labels[] = $store->name;
                $data[] = $count;
                $backgroundColor[] = $colors[$index % count($colors)];
            }
        }

        // 流入元不明の顧客も含める
        $unknownCount = Customer::whereNotNull('line_user_id')
                              ->whereNull('line_registration_store_id')
                              ->where('line_registered_at', '>=', $startDate)
                              ->count();
        
        if ($unknownCount > 0) {
            $labels[] = '流入元不明';
            $data[] = $unknownCount;
            $backgroundColor[] = 'rgba(108, 117, 125, 0.8)';
        }

        return [
            'datasets' => [
                [
                    'label' => 'LINE登録数',
                    'data' => $data,
                    'backgroundColor' => $backgroundColor,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getFilters(): ?array
    {
        return [
            'week' => '今週',
            'month' => '今月',
            'quarter' => '今四半期',
            'year' => '今年',
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}