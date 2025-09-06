<?php

namespace App\Filament\Resources\ReservationCalendarResource\Pages;

use App\Filament\Resources\ReservationCalendarResource;
use App\Filament\Widgets\ReservationCalendarWidget;
use App\Filament\Widgets\TodayReservationTimelineWidget;
use App\Models\Store;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\Page;

class ViewReservationCalendar extends Page
{
    protected static string $resource = ReservationCalendarResource::class;
    
    protected static string $view = 'filament.resources.reservation-calendar.pages.view-reservation-calendar';
    
    protected ?string $heading = '予約カレンダー';
    
    protected ?string $subheading = '予約状況を一覧で確認できます';
    
    public ?int $selectedStoreId = null;
    
    public function mount(): void
    {
        $user = auth()->user();
        
        if ($user->hasRole('super_admin')) {
            $firstStore = Store::first();
            $this->selectedStoreId = $firstStore?->id;
        } else {
            $this->selectedStoreId = $user->store_id;
        }
    }
    
    public function updatedSelectedStoreId($value): void
    {
        $this->dispatch('storeChanged', storeId: $value);
    }
    
    protected function getHeaderActions(): array
    {
        // ヘッダーアクションは使わず、ビューのセレクトボックスを使用
        return [];
    }
    
    protected function getHeaderWidgets(): array
    {
        return [
            // TodayReservationTimelineWidget::class,
            ReservationCalendarWidget::make([
                'selectedStoreId' => $this->selectedStoreId,
            ]),
        ];
    }
    
    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }
}