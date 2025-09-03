<?php

namespace App\Filament\Resources\ShiftResource\Pages;

use App\Filament\Resources\ShiftResource;
use App\Models\Shift;
use App\Models\User;
use App\Models\Store;
use App\Models\ShiftPattern;
use Filament\Resources\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateBulkShifts extends Page implements HasForms
{
    use InteractsWithForms;
    
    protected static string $resource = ShiftResource::class;
    protected static string $view = 'filament.resources.shift-resource.pages.create-bulk-shifts';
    
    public $selectedStore = null;
    public $selectedMonth;
    public $selectedYear;
    public $selectedDates = [];
    public $staffShifts = [];
    public $copyFromWeek = null;
    public $selectedPattern = null;
    public $quickPatterns = [
        'full' => ['start' => '10:00', 'end' => '20:00', 'name' => '終日'],
        'morning' => ['start' => '10:00', 'end' => '15:00', 'name' => '朝番'],
        'evening' => ['start' => '15:00', 'end' => '20:00', 'name' => '遅番'],
    ];
    
    public function mount(): void
    {
        $this->selectedStore = Store::where('is_active', true)->first()?->id;
        $this->selectedMonth = now()->month;
        $this->selectedYear = now()->year;
        $this->initializeStaffShifts();
    }
    
    public function initializeStaffShifts(): void
    {
        $staff = User::where('is_active_staff', true)
            ->orWhere('role', 'staff')
            ->get();
        $this->staffShifts = [];
        
        foreach ($staff as $user) {
            $this->staffShifts[$user->id] = [
                'name' => mb_convert_encoding($user->name, 'UTF-8', 'auto'),
                'store_id' => $user->store_id,
                'enabled' => false,
                'pattern' => '',
                'start_time' => '10:00',
                'end_time' => '20:00',
                'break_start' => null,
                'break_end' => null,
            ];
        }
    }
    
    public function selectWeekdays(): void
    {
        $this->selectedDates = [];
        $start = Carbon::create($this->selectedYear, $this->selectedMonth, 1);
        $end = $start->copy()->endOfMonth();
        
        while ($start <= $end) {
            if ($start->isWeekday()) {
                $this->selectedDates[] = $start->format('Y-m-d');
            }
            $start->addDay();
        }
    }
    
    public function selectWeekends(): void
    {
        $this->selectedDates = [];
        $start = Carbon::create($this->selectedYear, $this->selectedMonth, 1);
        $end = $start->copy()->endOfMonth();
        
        while ($start <= $end) {
            if ($start->isWeekend()) {
                $this->selectedDates[] = $start->format('Y-m-d');
            }
            $start->addDay();
        }
    }
    
    public function selectAllDays(): void
    {
        $this->selectedDates = [];
        $start = Carbon::create($this->selectedYear, $this->selectedMonth, 1);
        $end = $start->copy()->endOfMonth();
        
        while ($start <= $end) {
            $this->selectedDates[] = $start->format('Y-m-d');
            $start->addDay();
        }
    }
    
    public function clearSelection(): void
    {
        $this->selectedDates = [];
    }
    
    public function toggleDate($date): void
    {
        // 過去の日付はスキップ
        if (Carbon::parse($date)->isPast()) {
            return;
        }
        
        if (in_array($date, $this->selectedDates)) {
            $this->selectedDates = array_values(array_filter($this->selectedDates, fn($d) => $d !== $date));
        } else {
            $this->selectedDates[] = $date;
        }
    }
    
    public function applyPattern($userId, $pattern): void
    {
        if (isset($this->quickPatterns[$pattern])) {
            $this->staffShifts[$userId]['pattern'] = $pattern;
            $this->staffShifts[$userId]['start_time'] = $this->quickPatterns[$pattern]['start'];
            $this->staffShifts[$userId]['end_time'] = $this->quickPatterns[$pattern]['end'];
            
            // 自動休憩時間設定
            $hours = Carbon::parse($this->staffShifts[$userId]['start_time'])
                ->diffInHours(Carbon::parse($this->staffShifts[$userId]['end_time']));
            
            if ($hours >= 8) {
                $breakStart = Carbon::parse($this->staffShifts[$userId]['start_time'])->addHours(4);
                $this->staffShifts[$userId]['break_start'] = $breakStart->format('H:i');
                $this->staffShifts[$userId]['break_end'] = $breakStart->addMinutes(60)->format('H:i');
            } elseif ($hours >= 6) {
                $breakStart = Carbon::parse($this->staffShifts[$userId]['start_time'])->addHours(3);
                $this->staffShifts[$userId]['break_start'] = $breakStart->format('H:i');
                $this->staffShifts[$userId]['break_end'] = $breakStart->addMinutes(45)->format('H:i');
            }
        }
    }
    
    public function copyFromLastWeek(): void
    {
        $targetWeekStart = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->startOfWeek();
        $sourceWeekStart = $targetWeekStart->copy()->subWeek();
        
        // 先週のシフトを取得
        $lastWeekShifts = Shift::whereBetween('shift_date', [
                $sourceWeekStart,
                $sourceWeekStart->copy()->endOfWeek()
            ])
            ->get()
            ->groupBy('user_id');
        
        // 各スタッフのシフトをコピー
        foreach ($lastWeekShifts as $userId => $shifts) {
            if (isset($this->staffShifts[$userId])) {
                $this->staffShifts[$userId]['enabled'] = true;
                
                // 最も頻繁な時間帯を取得
                $mostCommon = $shifts->groupBy(function($shift) {
                    return $shift->start_time . '-' . $shift->end_time;
                })->sortByDesc(function($group) {
                    return $group->count();
                })->first();
                
                if ($mostCommon->isNotEmpty()) {
                    $sample = $mostCommon->first();
                    $this->staffShifts[$userId]['start_time'] = Carbon::parse($sample->start_time)->format('H:i');
                    $this->staffShifts[$userId]['end_time'] = Carbon::parse($sample->end_time)->format('H:i');
                    
                    if ($sample->break_start) {
                        $this->staffShifts[$userId]['break_start'] = Carbon::parse($sample->break_start)->format('H:i');
                        $this->staffShifts[$userId]['break_end'] = Carbon::parse($sample->break_end)->format('H:i');
                    }
                }
            }
        }
        
        // 先週と同じ曜日を選択
        foreach ($shifts->first() as $shift) {
            $dayOfWeek = $shift->shift_date->dayOfWeek;
            $start = Carbon::create($this->selectedYear, $this->selectedMonth, 1);
            $end = $start->copy()->endOfMonth();
            
            while ($start <= $end) {
                if ($start->dayOfWeek === $dayOfWeek) {
                    $dateStr = $start->format('Y-m-d');
                    if (!in_array($dateStr, $this->selectedDates)) {
                        $this->selectedDates[] = $dateStr;
                    }
                }
                $start->addDay();
            }
        }
        
        Notification::make()
            ->title('先週のシフトをコピーしました')
            ->success()
            ->send();
    }
    
    public function save(): void
    {
        if (empty($this->selectedDates)) {
            Notification::make()
                ->title('エラー')
                ->body('日付を選択してください')
                ->danger()
                ->send();
            return;
        }
        
        $enabledStaff = collect($this->staffShifts)->filter(fn($s) => $s['enabled']);
        if ($enabledStaff->isEmpty()) {
            Notification::make()
                ->title('エラー')
                ->body('スタッフを選択してください')
                ->danger()
                ->send();
            return;
        }
        
        DB::beginTransaction();
        try {
            $created = 0;
            $updated = 0;
            
            foreach ($this->selectedDates as $date) {
                foreach ($this->staffShifts as $userId => $shiftData) {
                    if (!$shiftData['enabled']) continue;
                    
                    // デフォルト店舗IDを設定
                    $storeId = $shiftData['store_id'] ?? $this->selectedStore;
                    if (!$storeId) {
                        $storeId = Store::where('is_active', true)->first()?->id;
                    }
                    
                    if (!$storeId) {
                        throw new \Exception('店舗IDが見つかりません');
                    }
                    
                    $existing = Shift::where('shift_date', $date)
                        ->where('user_id', $userId)
                        ->where('store_id', $storeId)
                        ->first();
                    
                    $data = [
                        'store_id' => $storeId,
                        'user_id' => $userId,
                        'shift_date' => $date,
                        'start_time' => $shiftData['start_time'] . ':00',
                        'end_time' => $shiftData['end_time'] . ':00',
                        'break_start' => $shiftData['break_start'] ? $shiftData['break_start'] . ':00' : null,
                        'break_end' => $shiftData['break_end'] ? $shiftData['break_end'] . ':00' : null,
                        'status' => 'scheduled',
                        'is_available_for_reservation' => true,
                    ];
                    
                    if ($existing) {
                        $existing->update($data);
                        $updated++;
                    } else {
                        Shift::create($data);
                        $created++;
                    }
                }
            }
            
            DB::commit();
            
            Notification::make()
                ->title('シフトを一括登録しました')
                ->body("新規: {$created}件 / 更新: {$updated}件")
                ->success()
                ->send();
            
            $this->redirect(ShiftResource::getUrl('index'));
            
        } catch (\Exception $e) {
            DB::rollback();
            
            \Log::error('Shift bulk creation error: ' . $e->getMessage(), [
                'selectedDates' => $this->selectedDates,
                'enabledStaff' => $enabledStaff->toArray(),
                'trace' => $e->getTraceAsString()
            ]);
            
            Notification::make()
                ->title('エラー')
                ->body('シフトの登録に失敗しました: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    public function changeMonth($direction): void
    {
        if ($direction === 'prev') {
            if ($this->selectedMonth == 1) {
                $this->selectedMonth = 12;
                $this->selectedYear--;
            } else {
                $this->selectedMonth--;
            }
        } else {
            if ($this->selectedMonth == 12) {
                $this->selectedMonth = 1;
                $this->selectedYear++;
            } else {
                $this->selectedMonth++;
            }
        }
        
        $this->selectedDates = [];
    }
}