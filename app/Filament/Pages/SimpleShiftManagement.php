<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Store;
use App\Models\Shift;
use App\Models\User;
use App\Models\ShiftPattern;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class SimpleShiftManagement extends Page implements HasForms
{
    use InteractsWithForms;
    
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'シフト管理';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.simple-shift-management';
    protected static ?string $navigationGroup = 'スタッフ管理';
    protected static ?string $title = 'シフト管理';
    
    public function getTitle(): string 
    {
        return 'シフト管理';
    }
    
    public $selectedStore = null;
    public $currentMonth;
    public $currentYear;
    public $stores = [];
    public $calendarData = [];
    public $staffList = [];
    public $patterns = [];
    public $todayShifts = []; // 今日の勤務者リスト
    public $todayTimeline = []; // 今日のタイムライン表示用
    public $timeSlots = []; // 時間スロット
    public $timelineDate; // タイムライン表示の日付
    
    // モーダル用
    public $showQuickAdd = false;
    public $quickAddDate = null;
    public $quickAddStaff = null;
    public $quickAddPattern = null;
    public $quickAddBreaks = []; // 複数の休憩時間
    
    // 編集モーダル用
    public $showEditModal = false;
    public $editingShiftId = null;
    public $editingShift = null;
    public $editingBreaks = [];
    
    // 一括登録用
    public $selectedDates = [];
    public $showBulkModal = false;
    public $bulkStaff = null;
    public $bulkPattern = null;
    public $bulkBreaks = [];
    public $isSelectMode = false; // 複数選択モード
    
    public static function canAccess(): bool
    {
        return Auth::user()->canAccessShiftManagement();
    }
    
    public function mount(): void
    {
        $user = Auth::user();
        
        // アクセス可能な店舗を取得
        $this->stores = $user->getAccessibleStores()->get();
        $this->selectedStore = $this->stores->first()?->id;
        
        $this->currentMonth = now()->month;
        $this->currentYear = now()->year;
        $this->timelineDate = now()->format('Y-m-d'); // 初期値は今日
        
        // 時間スロットを生成（10:00-19:00）
        $this->generateTimeSlots();
        
        // シフトパターンを読み込み
        $this->loadPatterns();
        $this->loadData();
    }
    
    private function generateTimeSlots(): void
    {
        $this->timeSlots = [];
        
        // 店舗の営業時間を取得
        $store = Store::find($this->selectedStore);
        if (!$store) {
            // デフォルト10:00-20:00
            $start = Carbon::createFromTime(10, 0);
            $end = Carbon::createFromTime(20, 0);
        } else {
            // タイムライン表示日の曜日の営業時間を取得
            $targetDate = $this->timelineDate ? Carbon::parse($this->timelineDate) : now();
            $dayOfWeek = $targetDate->format('l');
            $dayMapping = [
                'Monday' => 'monday',
                'Tuesday' => 'tuesday',
                'Wednesday' => 'wednesday',
                'Thursday' => 'thursday',
                'Friday' => 'friday',
                'Saturday' => 'saturday',
                'Sunday' => 'sunday',
            ];
            $dayKey = $dayMapping[$dayOfWeek] ?? 'monday';
            
            $businessHours = $store->business_hours ?? [];
            $todayHours = null;
            
            foreach ($businessHours as $hours) {
                if (isset($hours['day']) && $hours['day'] === $dayKey) {
                    $todayHours = $hours;
                    break;
                }
            }
            
            if ($todayHours && !empty($todayHours['open_time']) && !empty($todayHours['close_time'])) {
                $start = Carbon::createFromTimeString($todayHours['open_time']);
                $end = Carbon::createFromTimeString($todayHours['close_time']);
            } else {
                // デフォルト10:00-20:00
                $start = Carbon::createFromTime(10, 0);
                $end = Carbon::createFromTime(20, 0);
            }
        }
        
        // 15分刻みで時間スロットを生成
        // 終了時間の時まで完全に4分割する（例：21時終了なら20:45まで）
        while ($start->hour < $end->hour) {
            $this->timeSlots[] = $start->format('H:i');
            $start->addMinutes(15); // 15分刻み
        }
    }
    
    public function loadPatterns(): void
    {
        if (!$this->selectedStore) {
            $this->patterns = [];
            return;
        }
        
        $store = Store::find($this->selectedStore);
        $settings = $store->settings ?? [];
        $templates = $settings['shift_templates'] ?? [];
        
        if (empty($templates)) {
            // デフォルトパターン（店舗設定がない場合）
            $this->patterns = [
                ['id' => 1, 'name' => '早番', 'start' => '09:00', 'end' => '14:00'],
                ['id' => 2, 'name' => '遅番', 'start' => '14:00', 'end' => '21:00'],
                ['id' => 3, 'name' => '通常', 'start' => '10:00', 'end' => '19:00'],
                ['id' => 4, 'name' => '短時間', 'start' => '10:00', 'end' => '15:00'],
            ];
        } else {
            // 店舗設定のテンプレートを使用
            $this->patterns = [];
            foreach ($templates as $index => $template) {
                $this->patterns[] = [
                    'id' => $index + 1,
                    'name' => $template['name'],
                    'start' => $template['start_time'],
                    'end' => $template['end_time'],
                ];
            }
        }
    }
    
    public function loadData(): void
    {
        if (!$this->selectedStore) return;
        
        $store = Store::find($this->selectedStore);
        $user = Auth::user();
        
        // 編集可能なスタッフリストを取得
        $this->staffList = $user->getEditableStaff($store)->get();
        
        // 店舗が変更されたときに時間スロットを再生成
        $this->generateTimeSlots();
        
        $this->loadCalendarData();
    }
    
    public function loadCalendarData(): void
    {
        if (!$this->selectedStore) return;
        
        $startDate = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $endDate = $startDate->copy()->endOfMonth();
        
        // 今日の勤務者を取得
        $this->loadTodayShifts();
        
        // シフトデータを取得
        $shifts = Shift::with('user')
            ->where('store_id', $this->selectedStore)
            ->whereBetween('shift_date', [$startDate, $endDate])
            ->get()
            ->groupBy(function($shift) {
                return $shift->shift_date->format('Y-m-d');
            });
        
        // カレンダーデータを構築（簡略版）
        $this->calendarData = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');
            $dayShifts = $shifts->get($dateKey, collect());
            
            $this->calendarData[$dateKey] = [
                'date' => $currentDate->copy(),
                'dayOfWeek' => $currentDate->dayOfWeek,
                'isToday' => $currentDate->isToday(),
                'shifts' => $dayShifts->map(function($shift) {
                    return [
                        'id' => $shift->id,
                        'user_name' => $shift->user ? $shift->user->name : 'Unknown',
                        'time' => Carbon::parse($shift->start_time)->format('H:i') . '-' . 
                                 Carbon::parse($shift->end_time)->format('H:i'),
                    ];
                })->toArray(),
            ];
            
            $currentDate->addDay();
        }
    }
    
    public function loadTodayShifts(): void
    {
        // タイムライン表示の日付を使用（今日とは限らない）
        $date = $this->timelineDate ?: now()->format('Y-m-d');
        
        $shifts = Shift::with('user')
            ->where('store_id', $this->selectedStore)
            ->whereDate('shift_date', $date)
            ->orderBy('start_time')
            ->get();
            
        $this->todayShifts = $shifts->map(function($shift) {
            return [
                'name' => $shift->user ? $shift->user->name : '未割当',
                'time' => Carbon::parse($shift->start_time)->format('H:i') . '〜' . 
                         Carbon::parse($shift->end_time)->format('H:i'),
                'status' => $shift->status,
            ];
        })->toArray();
        
        // タイムラインデータを生成
        $this->generateTodayTimeline($shifts);
    }
    
    private function generateTodayTimeline($shifts): void
    {
        // 初期化（席情報は削除、スタッフのみ）
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
                    // 現在の日付に時間を設定
                    $targetDate = $this->timelineDate ? Carbon::parse($this->timelineDate) : now();
                    $slotTime = $targetDate->copy()->setTimeFromTimeString($time . ':00');
                    $startTime = $targetDate->copy()->setTimeFromTimeString($staffShift->start_time);
                    $endTime = $targetDate->copy()->setTimeFromTimeString($staffShift->end_time);
                    
                    // スロット時間がシフト時間内かチェック（15分刻みの場合、次のスロットまでの範囲を考慮）
                    $slotEndTime = $slotTime->copy()->addMinutes(15);
                    
                    if ($slotTime >= $startTime && $slotTime < $endTime) {
                        // 全ての休憩時間をチェック
                        $isBreak = false;
                        
                        // メインの休憩時間
                        if ($staffShift->break_start && $staffShift->break_end) {
                            $breakStart = $targetDate->copy()->setTimeFromTimeString($staffShift->break_start);
                            $breakEnd = $targetDate->copy()->setTimeFromTimeString($staffShift->break_end);
                            
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
                                    $breakStart = $targetDate->copy()->setTimeFromTimeString($break['start'] . ':00');
                                    $breakEnd = $targetDate->copy()->setTimeFromTimeString($break['end'] . ':00');
                                    
                                    if ($slotTime >= $breakStart && $slotTime < $breakEnd) {
                                        $isBreak = true;
                                        break;
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
        // シフトパターンを再読み込み
        $this->loadPatterns();
        $this->loadData();
    }
    
    public function previousTimelineDay(): void
    {
        $this->timelineDate = Carbon::parse($this->timelineDate)->subDay()->format('Y-m-d');
        $this->generateTimeSlots(); // 曜日が変わった場合に営業時間を再取得
        $this->loadTodayShifts();
    }
    
    public function nextTimelineDay(): void
    {
        $this->timelineDate = Carbon::parse($this->timelineDate)->addDay()->format('Y-m-d');
        $this->generateTimeSlots(); // 曜日が変わった場合に営業時間を再取得
        $this->loadTodayShifts();
    }
    
    public function goToToday(): void
    {
        $this->timelineDate = now()->format('Y-m-d');
        $this->generateTimeSlots();
        $this->loadTodayShifts();
    }
    
    public function openQuickAdd($date): void
    {
        $this->quickAddDate = $date;
        $this->quickAddBreaks = []; // デフォルトで休憩なし
        $this->showQuickAdd = true;
    }
    
    public function addBreak(): void
    {
        $this->quickAddBreaks[] = ['start' => '15:00', 'end' => '15:30'];
    }
    
    public function removeBreak($index): void
    {
        unset($this->quickAddBreaks[$index]);
        $this->quickAddBreaks = array_values($this->quickAddBreaks);
    }
    
    // シフト編集機能
    public function openEditModal($shiftId): void
    {
        $this->editingShiftId = $shiftId;
        $this->editingShift = Shift::with('user')->find($shiftId);
        
        if (!$this->editingShift) {
            Notification::make()
                ->title('エラー')
                ->body('シフトが見つかりません')
                ->danger()
                ->send();
            return;
        }
        
        // 権限チェック：スタッフは先月のシフトを編集不可（毎月5日以降）
        $user = Auth::user();
        $shiftDate = Carbon::parse($this->editingShift->shift_date);
        $now = now();
        
        if ($user->hasRole(['staff', 'manager'])) {
            // 今日が5日以降で、シフトが先月以前の場合
            if ($now->day >= 5) {
                $lastMonth = $now->copy()->subMonth();
                if ($shiftDate->year < $now->year || 
                    ($shiftDate->year == $now->year && $shiftDate->month < $now->month)) {
                    Notification::make()
                        ->title('編集不可')
                        ->body('毎月5日以降は先月以前のシフトは編集できません')
                        ->warning()
                        ->send();
                    return;
                }
            }
        }
        
        // 既存の休憩時間を読み込み
        $this->editingBreaks = [];
        
        // メインの休憩時間
        if ($this->editingShift->break_start && $this->editingShift->break_end) {
            $this->editingBreaks[] = [
                'start' => $this->editingShift->break_start,
                'end' => $this->editingShift->break_end
            ];
        }
        
        // 追加の休憩時間
        if ($this->editingShift->additional_breaks) {
            // additional_breaksは既に配列としてキャストされている
            $additionalBreaks = is_array($this->editingShift->additional_breaks) 
                ? $this->editingShift->additional_breaks 
                : json_decode($this->editingShift->additional_breaks, true);
            if ($additionalBreaks) {
                $this->editingBreaks = array_merge($this->editingBreaks, $additionalBreaks);
            }
        }
        
        // 休憩がない場合は空配列のまま（デフォルトで休憩なし）
        // if (empty($this->editingBreaks)) {
        //     $this->editingBreaks[] = ['start' => '12:00', 'end' => '13:00'];
        // }
        
        $this->showEditModal = true;
    }
    
    public function addEditBreak(): void
    {
        $this->editingBreaks[] = ['start' => '15:00', 'end' => '15:30'];
    }
    
    public function removeEditBreak($index): void
    {
        unset($this->editingBreaks[$index]);
        $this->editingBreaks = array_values($this->editingBreaks);
    }
    
    public function updateShift(): void
    {
        if (!$this->editingShift) return;
        
        // 権限チェック
        if (!Auth::user()->canEditShift($this->editingShift)) {
            Notification::make()
                ->title('権限エラー')
                ->body('このシフトを編集する権限がありません')
                ->danger()
                ->send();
            return;
        }
        
        // 最初の休憩時間をメインフィールドに、追加分はJSONで保存
        $firstBreak = !empty($this->editingBreaks) ? $this->editingBreaks[0] : null;
        $additionalBreaks = count($this->editingBreaks) > 1 ? array_slice($this->editingBreaks, 1) : [];
        
        $this->editingShift->update([
            'break_start' => $firstBreak ? $firstBreak['start'] : null,
            'break_end' => $firstBreak ? $firstBreak['end'] : null,
            'additional_breaks' => !empty($additionalBreaks) ? json_encode($additionalBreaks) : null,
        ]);
        
        Notification::make()
            ->title('更新完了')
            ->body('休憩時間を更新しました')
            ->success()
            ->send();
        
        $this->showEditModal = false;
        $this->editingShiftId = null;
        $this->editingShift = null;
        $this->editingBreaks = [];
        $this->loadCalendarData();
    }
    
    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editingShiftId = null;
        $this->editingShift = null;
        $this->editingBreaks = [];
    }
    
    // 一括登録機能
    public function toggleSelectMode(): void
    {
        $this->isSelectMode = !$this->isSelectMode;
        if (!$this->isSelectMode) {
            $this->selectedDates = [];
        }
    }
    
    public function handleDateClick($date): void
    {
        if ($this->isSelectMode) {
            // 選択モードの場合は日付を選択/選択解除
            if (in_array($date, $this->selectedDates)) {
                $this->selectedDates = array_values(array_diff($this->selectedDates, [$date]));
            } else {
                $this->selectedDates[] = $date;
            }
        } else {
            // 通常モードの場合は単一シフト追加
            $this->openQuickAdd($date);
        }
    }
    
    public function openBulkModal(): void
    {
        if (empty($this->selectedDates)) {
            Notification::make()
                ->title('エラー')
                ->body('日付を選択してください')
                ->warning()
                ->send();
            return;
        }
        
        $this->bulkBreaks = [];
        $this->showBulkModal = true;
    }
    
    public function addBulkBreak(): void
    {
        $this->bulkBreaks[] = ['start' => '15:00', 'end' => '15:30'];
    }
    
    public function removeBulkBreak($index): void
    {
        unset($this->bulkBreaks[$index]);
        $this->bulkBreaks = array_values($this->bulkBreaks);
    }
    
    public function bulkAddShifts(): void
    {
        if (!$this->bulkStaff || !$this->bulkPattern) {
            Notification::make()
                ->title('入力エラー')
                ->body('必要な項目を選択してください')
                ->danger()
                ->send();
            return;
        }
        
        $pattern = collect($this->patterns)->firstWhere('id', $this->bulkPattern);
        if (!$pattern) return;
        
        $user = User::find($this->bulkStaff);
        
        // 権限チェック
        if (!Auth::user()->canCreateShift(Store::find($this->selectedStore))) {
            Notification::make()
                ->title('権限エラー')
                ->body('シフトを作成する権限がありません')
                ->danger()
                ->send();
            return;
        }
        
        $successCount = 0;
        $updateCount = 0;
        
        // 最初の休憩時間をメインフィールドに、追加分はJSONで保存
        $firstBreak = !empty($this->bulkBreaks) ? $this->bulkBreaks[0] : null;
        $additionalBreaks = count($this->bulkBreaks) > 1 ? array_slice($this->bulkBreaks, 1) : [];
        
        foreach ($this->selectedDates as $date) {
            $existingShift = Shift::where('shift_date', $date)
                ->where('user_id', $this->bulkStaff)
                ->first();
                
            if ($existingShift) {
                // 既存のシフトを更新
                $existingShift->update([
                    'store_id' => $this->selectedStore,
                    'start_time' => $pattern['start'],
                    'end_time' => $pattern['end'],
                    'break_start' => $firstBreak ? $firstBreak['start'] : null,
                    'break_end' => $firstBreak ? $firstBreak['end'] : null,
                    'additional_breaks' => !empty($additionalBreaks) ? json_encode($additionalBreaks) : null,
                    'status' => 'scheduled',
                    'is_available_for_reservation' => true,
                ]);
                $updateCount++;
            } else {
                // 新規作成
                try {
                    Shift::create([
                        'store_id' => $this->selectedStore,
                        'user_id' => $this->bulkStaff,
                        'shift_date' => $date,
                        'start_time' => $pattern['start'],
                        'end_time' => $pattern['end'],
                        'break_start' => $firstBreak ? $firstBreak['start'] : null,
                        'break_end' => $firstBreak ? $firstBreak['end'] : null,
                        'additional_breaks' => !empty($additionalBreaks) ? json_encode($additionalBreaks) : null,
                        'status' => 'scheduled',
                        'is_available_for_reservation' => true,
                    ]);
                    $successCount++;
                } catch (\Exception $e) {
                    // エラーは無視して続行
                }
            }
        }
        
        Notification::make()
            ->title('一括登録完了')
            ->body($user->name . 'のシフトを' . $successCount . '件登録、' . $updateCount . '件更新しました')
            ->success()
            ->send();
        
        $this->showBulkModal = false;
        $this->selectedDates = [];
        $this->bulkStaff = null;
        $this->bulkPattern = null;
        $this->bulkBreaks = [];
        $this->loadCalendarData();
    }
    
    public function closeBulkModal(): void
    {
        $this->showBulkModal = false;
        $this->bulkStaff = null;
        $this->bulkPattern = null;
        $this->bulkBreaks = [];
    }
    
    public function clearSelection(): void
    {
        $this->selectedDates = [];
    }
    
    public function exitSelectMode(): void
    {
        $this->isSelectMode = false;
        $this->selectedDates = [];
    }
    
    public function quickAddShift(): void
    {
        if (!$this->quickAddDate || !$this->quickAddStaff || !$this->quickAddPattern) {
            Notification::make()
                ->title('入力エラー')
                ->body('必要な項目を選択してください')
                ->danger()
                ->send();
            return;
        }
        
        $pattern = collect($this->patterns)->firstWhere('id', $this->quickAddPattern);
        $user = User::find($this->quickAddStaff);
        
        // 権限チェック
        if (!Auth::user()->canCreateShift(Store::find($this->selectedStore))) {
            Notification::make()
                ->title('権限エラー')
                ->body('シフトを作成する権限がありません')
                ->danger()
                ->send();
            return;
        }
        
        // 既存チェック（store_idに関係なくuser_idとshift_dateの組み合わせでチェック）
        $existingShift = Shift::where('shift_date', $this->quickAddDate)
            ->where('user_id', $this->quickAddStaff)
            ->first();
            
        if ($existingShift) {
            // 既存のシフトを更新
            // 最初の休憩時間をメインフィールドに、追加分はJSONで保存
            $firstBreak = !empty($this->quickAddBreaks) ? $this->quickAddBreaks[0] : null;
            $additionalBreaks = count($this->quickAddBreaks) > 1 ? array_slice($this->quickAddBreaks, 1) : [];
            
            $existingShift->update([
                'store_id' => $this->selectedStore,
                'start_time' => $pattern['start'],
                'end_time' => $pattern['end'],
                'break_start' => $firstBreak ? $firstBreak['start'] : null,
                'break_end' => $firstBreak ? $firstBreak['end'] : null,
                'additional_breaks' => !empty($additionalBreaks) ? json_encode($additionalBreaks) : null,
                'status' => 'scheduled',
                'is_available_for_reservation' => true,
            ]);
            
            Notification::make()
                ->title('更新完了')
                ->body($user->name . 'のシフトを更新しました')
                ->success()
                ->send();
        } else {
            // 新規作成
            try {
                // 最初の休憩時間をメインフィールドに、追加分はJSONで保存
                $firstBreak = !empty($this->quickAddBreaks) ? $this->quickAddBreaks[0] : null;
                $additionalBreaks = count($this->quickAddBreaks) > 1 ? array_slice($this->quickAddBreaks, 1) : [];
                
                Shift::create([
                    'store_id' => $this->selectedStore,
                    'user_id' => $this->quickAddStaff,
                    'shift_date' => $this->quickAddDate,
                    'start_time' => $pattern['start'],
                    'end_time' => $pattern['end'],
                    'break_start' => $firstBreak ? $firstBreak['start'] : null,
                    'break_end' => $firstBreak ? $firstBreak['end'] : null,
                    'additional_breaks' => !empty($additionalBreaks) ? json_encode($additionalBreaks) : null,
                    'status' => 'scheduled',
                    'is_available_for_reservation' => true,
                ]);
                
                Notification::make()
                    ->title('登録完了')
                    ->body($user->name . 'のシフトを登録しました')
                    ->success()
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->title('エラー')
                    ->body('シフトの登録に失敗しました: ' . $e->getMessage())
                    ->danger()
                    ->send();
                return;
            }
        }
        
        $this->showQuickAdd = false;
        $this->quickAddStaff = null;
        $this->quickAddPattern = null;
        $this->quickAddBreaks = [];
        $this->loadCalendarData();
    }
    
    public function deleteShift($shiftId): void
    {
        $shift = Shift::find($shiftId);
        
        if (!$shift || !Auth::user()->canDeleteShift($shift)) {
            Notification::make()
                ->title('権限エラー')
                ->body('このシフトを削除する権限がありません')
                ->danger()
                ->send();
            return;
        }
        
        // スタッフは先月のシフトを削除不可（毎月5日以降）
        $user = Auth::user();
        $shiftDate = Carbon::parse($shift->shift_date);
        $now = now();
        
        if ($user->hasRole(['staff', 'manager'])) {
            if ($now->day >= 5) {
                if ($shiftDate->year < $now->year || 
                    ($shiftDate->year == $now->year && $shiftDate->month < $now->month)) {
                    Notification::make()
                        ->title('削除不可')
                        ->body('毎月5日以降は先月以前のシフトは削除できません')
                        ->warning()
                        ->send();
                    return;
                }
            }
        }
        
        $shift->delete();
        
        Notification::make()
            ->title('削除完了')
            ->body('シフトを削除しました')
            ->success()
            ->send();
            
        $this->loadCalendarData();
    }
    
    public function applyPattern($weekNumber): void
    {
        // 週単位でパターンを一括適用
        $startOfWeek = Carbon::create($this->currentYear, $this->currentMonth, 1)
            ->addWeeks($weekNumber)->startOfWeek();
        
        for ($i = 0; $i < 7; $i++) {
            $date = $startOfWeek->copy()->addDays($i);
            
            // 平日は通常パターン、土日は早番/遅番を自動割り当て
            if ($date->isWeekday()) {
                // 通常パターンを適用
                foreach ($this->staffList as $index => $staff) {
                    if ($index < 2) { // 最初の2人だけ
                        $this->createShiftFromPattern($date, $staff->id, 3); // 通常パターン
                    }
                }
            }
        }
        
        $this->loadCalendarData();
        
        Notification::make()
            ->title('パターン適用完了')
            ->body('第' . ($weekNumber + 1) . '週にパターンを適用しました')
            ->success()
            ->send();
    }
    
    private function createShiftFromPattern($date, $userId, $patternId): void
    {
        $pattern = collect($this->patterns)->firstWhere('id', $patternId);
        
        if (!$pattern) return;
        
        // 既存チェック
        $exists = Shift::where('store_id', $this->selectedStore)
            ->where('shift_date', $date)
            ->where('user_id', $userId)
            ->exists();
            
        if (!$exists) {
            Shift::create([
                'store_id' => $this->selectedStore,
                'user_id' => $userId,
                'shift_date' => $date,
                'start_time' => $pattern['start'],
                'end_time' => $pattern['end'],
                'status' => 'scheduled',
                'is_available_for_reservation' => true,
            ]);
        }
    }
}