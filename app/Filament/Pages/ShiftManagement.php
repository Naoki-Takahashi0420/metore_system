<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Store;
use App\Models\Shift;
use App\Models\User;
use App\Models\ShiftPattern;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ShiftManagement extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'シフト管理（旧）';
    protected static ?int $navigationSort = 99;
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.shift-management';
    
    protected static ?string $navigationGroup = 'スタッフ管理';
    
    public $selectedStore = null;
    public $currentMonth;
    public $currentYear;
    public $stores = [];
    public $calendarData = [];
    public $staffList = [];
    
    public function mount(): void
    {
        $this->stores = Store::where('is_active', true)->get();
        $this->selectedStore = $this->stores->first()?->id;
        $this->currentMonth = now()->month;
        $this->currentYear = now()->year;
        $this->loadData();
    }
    
    public function loadData(): void
    {
        $this->loadStaffList();
        $this->loadCalendarData();
    }
    
    public function loadStaffList(): void
    {
        $this->staffList = User::where('is_active_staff', true)
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                $user->name = mb_convert_encoding($user->name ?? '', 'UTF-8', 'auto');
                return $user;
            });
    }
    
    public function loadCalendarData(): void
    {
        if (!$this->selectedStore) return;
        
        $startDate = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $endDate = $startDate->copy()->endOfMonth();
        $startWeek = $startDate->copy()->startOfWeek();
        $endWeek = $endDate->copy()->endOfWeek();
        
        // シフトデータを取得
        $shifts = Shift::with('user')
            ->where('store_id', $this->selectedStore)
            ->whereBetween('shift_date', [$startDate, $endDate])
            ->orderBy('shift_date')
            ->orderBy('start_time')
            ->get()
            ->groupBy(function($shift) {
                return $shift->shift_date->format('Y-m-d');
            });
        
        // カレンダーデータを構築
        $this->calendarData = [];
        $currentDate = $startWeek->copy();
        $weekIndex = 0;
        
        while ($currentDate <= $endWeek) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $dateKey = $currentDate->format('Y-m-d');
                $dayShifts = $shifts->get($dateKey, collect());
                
                $week[] = [
                    'date' => $currentDate->copy(),
                    'dateKey' => $dateKey,
                    'day' => $currentDate->day,
                    'isCurrentMonth' => $currentDate->month === $this->currentMonth,
                    'isToday' => $currentDate->isToday(),
                    'isPast' => $currentDate->isPast(),
                    'dayOfWeek' => $currentDate->dayOfWeek,
                    'shifts' => $dayShifts->map(function($shift) {
                        return [
                            'id' => $shift->id,
                            'user_id' => $shift->user_id,
                            'user_name' => $shift->user->name,
                            'start' => Carbon::parse($shift->start_time)->format('H:i'),
                            'end' => Carbon::parse($shift->end_time)->format('H:i'),
                            'status' => $shift->status,
                        ];
                    })->toArray(),
                ];
                
                $currentDate->addDay();
            }
            $this->calendarData[] = $week;
        }
    }
    
    public function previousMonth(): void
    {
        if ($this->currentMonth == 1) {
            $this->currentMonth = 12;
            $this->currentYear--;
        } else {
            $this->currentMonth--;
        }
        $this->loadCalendarData();
    }
    
    public function nextMonth(): void
    {
        if ($this->currentMonth == 12) {
            $this->currentMonth = 1;
            $this->currentYear++;
        } else {
            $this->currentMonth++;
        }
        $this->loadCalendarData();
    }
    
    public function changeStore(): void
    {
        $this->loadCalendarData();
    }
    
    public function quickAddShift($date, $userId, $startTime, $endTime): void
    {
        // スタッフの所属店舗を取得
        $user = User::find($userId);
        $storeId = $user->store_id ?? $this->selectedStore;
        
        // 既存チェック
        $existing = Shift::where('store_id', $storeId)
            ->where('shift_date', $date)
            ->where('user_id', $userId)
            ->first();
        
        if ($existing) {
            $existing->update([
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);
        } else {
            Shift::create([
                'store_id' => $storeId,
                'user_id' => $userId,
                'shift_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => 'scheduled',
                'is_available_for_reservation' => true,
            ]);
        }
        
        $this->loadCalendarData();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'シフトを追加しました',
        ]);
    }
    
    public function deleteShift($shiftId): void
    {
        Shift::find($shiftId)?->delete();
        $this->loadCalendarData();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'シフトを削除しました',
        ]);
    }
}