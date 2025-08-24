<?php

namespace App\Filament\Resources\ShiftResource\Pages;

use App\Filament\Resources\ShiftResource;
use App\Models\Shift;
use Filament\Resources\Pages\Page;
use Carbon\Carbon;
use Livewire\Attributes\Computed;

class ShiftCalendar extends Page
{
    protected static string $resource = ShiftResource::class;

    protected static string $view = 'filament.resources.shift-resource.pages.shift-calendar';
    
    protected static ?string $title = 'シフトカレンダー';
    
    public string $currentDate;
    public ?int $selectedStoreId = null;
    
    public function mount()
    {
        $this->currentDate = Carbon::now()->startOfWeek()->format('Y-m-d');
        
        // スーパーアドミン以外は自分の店舗を固定
        $user = auth()->user();
        if ($user->role !== 'superadmin') {
            $this->selectedStoreId = $user->store_id;
        }
    }
    
    #[Computed]
    public function shifts()
    {
        $startDate = Carbon::parse($this->currentDate)->startOfWeek();
        $endDate = $startDate->copy()->endOfWeek();
        
        $query = Shift::with(['user', 'store'])
            ->whereBetween('shift_date', [$startDate, $endDate]);
        
        // 店舗フィルター
        if ($this->selectedStoreId) {
            $query->where('store_id', $this->selectedStoreId);
        }
        
        return $query->get()
            ->groupBy(function($shift) {
                return $shift->shift_date->format('Y-m-d');
            });
    }
    
    #[Computed]
    public function stores()
    {
        return \App\Models\Store::all();
    }
    
    public function updatedSelectedStoreId()
    {
        // 店舗が変更されたら再描画
        $this->dispatch('refresh');
    }
    
    public function previousWeek()
    {
        $this->currentDate = Carbon::parse($this->currentDate)->subWeek()->format('Y-m-d');
    }
    
    public function nextWeek()
    {
        $this->currentDate = Carbon::parse($this->currentDate)->addWeek()->format('Y-m-d');
    }
    
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('list')
                ->label('リスト表示')
                ->icon('heroicon-o-list-bullet')
                ->url(ShiftResource::getUrl('index'))
                ->color('gray'),
        ];
    }
    
    protected function getViewData(): array
    {
        $startDate = Carbon::parse($this->currentDate)->startOfWeek();
        $endDate = $startDate->copy()->endOfWeek();
        
        // 週の日付配列を生成
        $weekDays = [];
        $current = $startDate->copy();
        
        while ($current <= $endDate) {
            $dateString = $current->format('Y-m-d');
            $weekDays[] = [
                'date' => $current->copy(),
                'dateString' => $dateString,
                'dayName' => $current->format('D'),
                'dayJa' => $this->getDayName($current->dayOfWeek),
                'isToday' => $current->isToday(),
                'shifts' => $this->shifts()[$dateString] ?? collect(),
            ];
            $current->addDay();
        }
        
        // 時間スロット生成（30分刻み）
        $timeSlots = [];
        for ($hour = 8; $hour <= 22; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 30) {
                $timeSlots[] = sprintf('%02d:%02d', $hour, $minute);
            }
        }
        
        return [
            'currentDate' => $this->currentDate,
            'weekStart' => $startDate->format('m月d日'),
            'weekEnd' => $endDate->format('m月d日'),
            'weekDays' => $weekDays,
            'timeSlots' => $timeSlots,
            'shifts' => $this->shifts(),
            'stores' => $this->stores(),
            'selectedStoreId' => $this->selectedStoreId,
            'isSuperAdmin' => auth()->user()->role === 'superadmin',
        ];
    }
    
    private function getDayName($dayOfWeek): string
    {
        return match($dayOfWeek) {
            0 => '日',
            1 => '月', 
            2 => '火',
            3 => '水',
            4 => '木',
            5 => '金',
            6 => '土',
        };
    }
}