<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Resources\SubscriptionResource;
use App\Filament\Widgets\SubscriptionStatsWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 新規作成ボタンを削除（顧客編集画面から作成）
        ];
    }
    
    protected function getHeaderWidgets(): array
    {
        return [
            SubscriptionStatsWidget::class,
        ];
    }
    
    public function getTitle(): string 
    {
        return 'サブスク契約管理';
    }
}