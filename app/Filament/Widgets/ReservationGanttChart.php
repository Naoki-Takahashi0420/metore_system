<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use App\Models\Store;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class ReservationGanttChart extends Widget
{
    protected static string $view = 'filament.widgets.reservation-gantt-chart';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 2;
    
    public $selectedDate;
    public $selectedStore;
    public $stores;
    public $reservations;
    public $timeSlots;
    public $staffList;
    
    public function mount(): void
    {
        $this->selectedDate = Carbon::today()->format('Y-m-d');
        $this->stores = Store::where('is_active', true)->get();
        $this->selectedStore = $this->stores->first()?->id;
        $this->loadReservations();
    }
    
    public function loadReservations(): void
    {
        if (!$this->selectedStore || !$this->selectedDate) {
            $this->reservations = collect();
            $this->staffList = [];
            $this->timeSlots = [];
            return;
        }
        
        // 予約データ取得
        $this->reservations = Reservation::with(['customer', 'menu', 'staff'])
            ->where('store_id', $this->selectedStore)
            ->whereDate('reservation_date', $this->selectedDate)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->orderBy('reservation_time')
            ->get();
        
        // スタッフリスト生成
        $this->staffList = $this->reservations
            ->pluck('staff')
            ->filter()
            ->unique('id')
            ->values();
        
        // スタッフなしの予約も表示
        if ($this->reservations->whereNull('staff_id')->isNotEmpty()) {
            $this->staffList->push((object)['id' => 0, 'name' => '未割当']);
        }
        
        // タイムスロット生成（9:00〜21:00、15分刻み）
        $this->timeSlots = [];
        $start = Carbon::createFromTime(9, 0);
        $end = Carbon::createFromTime(21, 0);
        
        while ($start <= $end) {
            $this->timeSlots[] = $start->format('H:i');
            $start->addMinutes(15);
        }
    }
    
    public function getReservationPosition($reservation): array
    {
        $time = Carbon::parse($reservation->reservation_time);
        $startMinutes = $time->hour * 60 + $time->minute;
        $baseMinutes = 9 * 60; // 9:00開始
        
        // 15分を1単位として計算
        $left = (($startMinutes - $baseMinutes) / 15) * 60; // 60px per 15min
        $width = ($reservation->menu->duration_minutes / 15) * 60;
        
        return [
            'left' => $left,
            'width' => $width,
        ];
    }
    
    public function getReservationsByStaff($staffId): Collection
    {
        return $this->reservations->filter(function ($reservation) use ($staffId) {
            if ($staffId === 0) {
                return is_null($reservation->staff_id);
            }
            return $reservation->staff_id === $staffId;
        });
    }
    
    public function updatedSelectedDate(): void
    {
        $this->loadReservations();
    }
    
    public function updatedSelectedStore(): void
    {
        $this->loadReservations();
    }
    
    public function previousDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->subDay()->format('Y-m-d');
        $this->loadReservations();
    }
    
    public function nextDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->addDay()->format('Y-m-d');
        $this->loadReservations();
    }
    
    public function today(): void
    {
        $this->selectedDate = Carbon::today()->format('Y-m-d');
        $this->loadReservations();
    }
}