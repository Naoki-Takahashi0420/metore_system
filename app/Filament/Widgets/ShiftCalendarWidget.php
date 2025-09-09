<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Store;
use App\Models\Shift;
use App\Models\User;
use App\Models\ShiftPattern;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class ShiftCalendarWidget extends Widget
{
    protected static string $view = 'filament.widgets.shift-calendar';
    
    protected int|string|array $columnSpan = 'full';
    
    protected static ?int $sort = 2;
    
    public $selectedStore = null;
    public $currentMonth;
    public $currentYear;
    public $stores = [];
    public $calendarData = [];
    public $selectedDate = null;
    public $staffList = [];
    public $patterns = [];
    public $monthlySummary = [];
    
    // 一括編集用
    public $bulkEditDates = [];
    public $bulkEditStaff = [];
    
    public function mount(): void
    {
        $this->stores = Store::where('is_active', true)->get();
        $this->selectedStore = $this->stores->first()?->id;
        $this->currentMonth = now()->month;
        $this->currentYear = now()->year;
        $this->loadCalendarData();
        $this->loadStaffList();
        $this->loadPatterns();
        $this->calculateMonthlySummary();
    }
    
    #[On('store-changed')]
    public function updateStore($storeId, $date = null): void
    {
        $this->selectedStore = $storeId;
        $this->loadCalendarData();
        $this->loadPatterns();
        $this->calculateMonthlySummary();
    }
    
    public function loadCalendarData(): void
    {
        if (!$this->selectedStore) return;
        
        $startDate = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $endDate = $startDate->copy()->endOfMonth();
        
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
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');
            $dayShifts = $shifts->get($dateKey, collect());
            
            $this->calendarData[$dateKey] = [
                'date' => $currentDate->copy(),
                'shifts' => $dayShifts->map(function($shift) {
                    return [
                        'id' => $shift->id,
                        'staff_name' => $shift->user ? $shift->user->name : '未割当',
                        'staff_id' => $shift->user_id,
                        'start' => Carbon::parse($shift->start_time)->format('H:i'),
                        'end' => Carbon::parse($shift->end_time)->format('H:i'),
                        'type' => $this->getShiftType($shift->start_time, $shift->end_time),
                        'status' => $shift->status,
                    ];
                })->toArray(),
                'is_today' => $currentDate->isToday(),
                'is_past' => $currentDate->isPast(),
                'day_of_week' => $currentDate->dayOfWeek,
            ];
            
            $currentDate->addDay();
        }
    }
    
    private function getShiftType($startTime, $endTime): string
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);
        $hours = $start->diffInHours($end);
        
        if ($hours >= 8) return 'full';
        if ($start->hour < 12) return 'morning';
        if ($start->hour < 15) return 'afternoon';
        return 'evening';
    }
    
    public function loadStaffList(): void
    {
        $this->staffList = User::where('is_active_staff', true)
            ->orderBy('name')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'can_be_nominated' => $user->can_be_nominated,
                    'default_hours' => $user->default_shift_hours,
                ];
            })->toArray();
    }
    
    public function loadPatterns(): void
    {
        if (!$this->selectedStore) return;
        
        $this->patterns = ShiftPattern::where('store_id', $this->selectedStore)
            ->orderBy('usage_count', 'desc')
            ->get()
            ->map(function($pattern) {
                return [
                    'id' => $pattern->id,
                    'name' => $pattern->name,
                    'description' => $pattern->description,
                    'is_default' => $pattern->is_default,
                    'usage_count' => $pattern->usage_count,
                ];
            })->toArray();
    }
    
    public function calculateMonthlySummary(): void
    {
        if (!$this->selectedStore) return;
        
        $startDate = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $endDate = $startDate->copy()->endOfMonth();
        
        $shifts = Shift::where('store_id', $this->selectedStore)
            ->whereBetween('shift_date', [$startDate, $endDate])
            ->get();
        
        // 総勤務時間
        $totalHours = $shifts->sum(function($shift) {
            $start = Carbon::parse($shift->start_time);
            $end = Carbon::parse($shift->end_time);
            $hours = $start->diffInHours($end);
            
            // 休憩時間を引く
            if ($shift->break_start && $shift->break_end) {
                $breakStart = Carbon::parse($shift->break_start);
                $breakEnd = Carbon::parse($shift->break_end);
                $hours -= $breakStart->diffInHours($breakEnd);
            }
            
            return $hours;
        });
        
        // 平均スタッフ数/日
        $daysWithShifts = $shifts->pluck('shift_date')->unique()->count();
        $avgStaffPerDay = $daysWithShifts > 0 ? round($shifts->count() / $daysWithShifts, 1) : 0;
        
        // シフト入力率
        $totalDays = $endDate->diffInDays($startDate) + 1;
        $inputRate = $totalDays > 0 ? round(($daysWithShifts / $totalDays) * 100) : 0;
        
        $this->monthlySummary = [
            'total_hours' => $totalHours,
            'avg_staff_per_day' => $avgStaffPerDay,
            'input_rate' => $inputRate,
        ];
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
        $this->calculateMonthlySummary();
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
        $this->calculateMonthlySummary();
    }
    
    public function openShiftEdit($date): void
    {
        $this->selectedDate = $date;
        $this->dispatch('open-shift-modal', date: $date);
    }
    
    public function saveShift($date, $staffId, $startTime, $endTime): void
    {
        // 既存のシフトを確認
        $existing = Shift::where('store_id', $this->selectedStore)
            ->where('shift_date', $date)
            ->where('user_id', $staffId)
            ->first();
        
        if ($existing) {
            if (!$startTime || !$endTime) {
                // 削除
                $existing->delete();
            } else {
                // 更新
                $existing->update([
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ]);
            }
        } else if ($startTime && $endTime) {
            // 新規作成
            Shift::create([
                'store_id' => $this->selectedStore,
                'user_id' => $staffId,
                'shift_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => 'scheduled',
                'is_available_for_reservation' => true,
            ]);
        }
        
        $this->loadCalendarData();
        $this->calculateMonthlySummary();
    }
    
    public function applyPattern($patternId, $dates): void
    {
        $pattern = ShiftPattern::find($patternId);
        if (!$pattern) return;
        
        foreach ($dates as $date) {
            $pattern->applyToDate($date, $this->selectedStore);
        }
        
        $this->loadCalendarData();
        $this->calculateMonthlySummary();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'パターンを適用しました',
        ]);
    }
    
    public function bulkSaveShifts($dates, $staffShifts): void
    {
        DB::transaction(function() use ($dates, $staffShifts) {
            foreach ($dates as $date) {
                foreach ($staffShifts as $staffId => $times) {
                    if ($times['start'] && $times['end']) {
                        $this->saveShift($date, $staffId, $times['start'], $times['end']);
                    }
                }
            }
        });
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'シフトを一括登録しました',
        ]);
    }
}