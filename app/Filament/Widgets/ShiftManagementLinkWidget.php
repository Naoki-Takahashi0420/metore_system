<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Store;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;

class ShiftManagementLinkWidget extends Widget
{
    protected static string $view = 'filament.widgets.shift-management-link';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 40;

    public static function canView(): bool
    {
        $user = auth()->user();
        return $user && !$user->hasRole('staff');
    }
    
    public $selectedStore = null;
    public $stores = [];
    public $todayShifts = [];
    public $todayTimeline = [];
    public $timeSlots = [];
    public $timelineDate;
    public $calendarData = [];
    public $currentMonth;
    public $currentYear;
    
    public function mount(): void
    {
        $user = Auth::user();
        
        // アクセス可能な店舗を取得
        $this->stores = $user->getAccessibleStores()->get();
        $this->selectedStore = $this->stores->first()?->id;
        
        $this->timelineDate = now()->format('Y-m-d');
        $this->currentMonth = now()->month;
        $this->currentYear = now()->year;
        
        $this->generateTimeSlots();
        $this->loadTimelineData();
        $this->loadCalendarData();
    }
    
    #[On('store-changed')]
    public function updateStore($storeId, $date = null): void
    {
        $this->selectedStore = $storeId;
        $this->generateTimeSlots(); // 店舗変更時に時間スロットを再生成
        $this->loadTimelineData();
        $this->loadCalendarData();
    }
    
    private function generateTimeSlots(): void
    {
        $this->timeSlots = [];

        // 店舗の営業時間を取得
        $startHour = 10;
        $endHour = 22;

        if ($this->selectedStore) {
            $store = Store::find($this->selectedStore);
            if ($store && $store->business_hours) {
                $businessHours = $store->business_hours;

                // 日付から曜日を取得
                $targetDate = Carbon::parse($this->timelineDate);
                $dayOfWeek = strtolower($targetDate->format('l'));

                // 曜日ごとの営業時間を確認
                if (is_array($businessHours)) {
                    foreach ($businessHours as $hours) {
                        if (isset($hours['day']) && $hours['day'] === $dayOfWeek) {
                            if (isset($hours['open_time']) && isset($hours['close_time'])) {
                                $startHour = (int)substr($hours['open_time'], 0, 2);
                                $endHour = (int)substr($hours['close_time'], 0, 2);

                                // 閉店時間が0分でない場合は+1時間（例：21:30なら22時まで表示）
                                $closeMinute = (int)substr($hours['close_time'], 3, 2);
                                if ($closeMinute > 0) {
                                    $endHour++;
                                }
                            }
                            break;
                        }
                    }
                }
            }

            // シフトの実際の時間範囲を確認して調整
            $shifts = Shift::where('store_id', $this->selectedStore)
                ->whereDate('shift_date', Carbon::parse($this->timelineDate))
                ->get();

            if ($shifts->count() > 0) {
                $earliestStart = $startHour;
                $latestEnd = $endHour;

                foreach ($shifts as $shift) {
                    $shiftStartHour = (int)substr($shift->start_time, 0, 2);
                    $shiftEndHour = (int)substr($shift->end_time, 0, 2);
                    $shiftEndMinute = (int)substr($shift->end_time, 3, 2);

                    if ($shiftStartHour < $earliestStart) {
                        $earliestStart = $shiftStartHour;
                    }
                    if ($shiftEndHour > $latestEnd || ($shiftEndHour == $latestEnd && $shiftEndMinute > 0)) {
                        $latestEnd = $shiftEndMinute > 0 ? $shiftEndHour + 1 : $shiftEndHour;
                    }
                }

                $startHour = $earliestStart;
                $endHour = $latestEnd;
            }
        }

        // 時間スロットを生成（15分刻み）
        for ($hour = $startHour; $hour < $endHour; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 15) {
                $this->timeSlots[] = sprintf('%02d:%02d', $hour, $minute);
            }
        }
    }
    
    public function loadTimelineData(): void
    {
        if (!$this->selectedStore) return;

        $targetDate = Carbon::parse($this->timelineDate);

        // その日のシフトを取得
        $shifts = Shift::with('user')
            ->where('store_id', $this->selectedStore)
            ->whereDate('shift_date', $targetDate)
            ->orderBy('start_time')
            ->get();

        $this->todayTimeline = [];
        
        // 全スタッフのリストを取得（シフトがなくても表示）
        $allStaff = User::where('is_active_staff', true)
            ->where('store_id', $this->selectedStore)
            ->orderBy('name')
            ->get();
        
        // スタッフごとのタイムラインを生成
        foreach ($allStaff as $staff) {
            // このスタッフのシフトを検索
            $staffShift = $shifts->where('user_id', $staff->id)->first();
            
            $staffTimeline = [
                'name' => $staff->name,
                'slots' => []
            ];
            
            foreach ($this->timeSlots as $time) {
                if ($staffShift) {
                    // setTimeFromTimeStringを使用して時間を設定
                    $slotTime = $targetDate->copy()->setTimeFromTimeString($time . ':00');
                    $startTime = $targetDate->copy()->setTimeFromTimeString($staffShift->start_time);
                    $endTime = $targetDate->copy()->setTimeFromTimeString($staffShift->end_time);
                    
                    if ($slotTime >= $startTime && $slotTime < $endTime) {
                        // 休憩時間チェック
                        $isBreak = false;
                        
                        // メインの休憩時間
                        if ($staffShift->break_start && $staffShift->break_end) {
                            // 時間形式を統一（秒があっても対応）
                            $breakStartStr = substr($staffShift->break_start, 0, 5); // HH:MM形式に変換
                            $breakEndStr = substr($staffShift->break_end, 0, 5);
                            
                            $breakStart = $targetDate->copy()->setTimeFromTimeString($breakStartStr . ':00');
                            $breakEnd = $targetDate->copy()->setTimeFromTimeString($breakEndStr . ':00');
                            
                            if ($slotTime >= $breakStart && $slotTime < $breakEnd) {
                                $isBreak = true;
                            }
                        }
                        
                        // 追加の休憩時間
                        if (!$isBreak && !empty($staffShift->additional_breaks)) {
                            $additionalBreaks = is_string($staffShift->additional_breaks) 
                                ? json_decode($staffShift->additional_breaks, true) 
                                : $staffShift->additional_breaks;
                            
                            if ($additionalBreaks && is_array($additionalBreaks)) {
                                foreach ($additionalBreaks as $break) {
                                    if (isset($break['start']) && isset($break['end'])) {
                                        $breakStart = $targetDate->copy()->setTimeFromTimeString($break['start'] . ':00');
                                        $breakEnd = $targetDate->copy()->setTimeFromTimeString($break['end'] . ':00');
                                        
                                        if ($slotTime >= $breakStart && $slotTime < $breakEnd) {
                                            $isBreak = true;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                        
                        $staffTimeline['slots'][$time] = $isBreak ? 'break' : 'working';
                    } else {
                        $staffTimeline['slots'][$time] = '';
                    }
                } else {
                    // シフトがない場合は空
                    $staffTimeline['slots'][$time] = '';
                }
            }
            
            $this->todayTimeline[] = $staffTimeline;
        }
        
        $this->todayShifts = $shifts;
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
        
        $this->calendarData = [];
        
        // カレンダーの日付ごとにシフト情報を構築
        $currentDate = $startDate->copy()->startOfMonth()->startOfWeek();
        $endDate = $startDate->copy()->endOfMonth()->endOfWeek();
        
        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');
            $this->calendarData[$dateKey] = [
                'date' => $currentDate->copy(),
                'shifts' => $shifts->get($dateKey, collect())->toArray(),
                'isCurrentMonth' => $currentDate->month === $this->currentMonth,
                'isToday' => $currentDate->isToday(),
            ];
            $currentDate->addDay();
        }
    }
    
    public function previousTimelineDay(): void
    {
        $this->timelineDate = Carbon::parse($this->timelineDate)->subDay()->format('Y-m-d');
        $this->generateTimeSlots(); // 日付変更時に時間スロットを再生成
        $this->loadTimelineData();
    }

    public function nextTimelineDay(): void
    {
        $this->timelineDate = Carbon::parse($this->timelineDate)->addDay()->format('Y-m-d');
        $this->generateTimeSlots(); // 日付変更時に時間スロットを再生成
        $this->loadTimelineData();
    }

    public function goToToday(): void
    {
        $this->timelineDate = now()->format('Y-m-d');
        $this->generateTimeSlots(); // 日付変更時に時間スロットを再生成
        $this->loadTimelineData();
    }
    
    public function previousMonth(): void
    {
        $date = Carbon::create($this->currentYear, $this->currentMonth);
        $date->subMonth();
        $this->currentMonth = $date->month;
        $this->currentYear = $date->year;
        $this->loadCalendarData();
    }
    
    public function nextMonth(): void
    {
        $date = Carbon::create($this->currentYear, $this->currentMonth);
        $date->addMonth();
        $this->currentMonth = $date->month;
        $this->currentYear = $date->year;
        $this->loadCalendarData();
    }
    
    public function getShiftManagementUrl(): string
    {
        return route('filament.admin.pages.simple-shift-management');
    }
}