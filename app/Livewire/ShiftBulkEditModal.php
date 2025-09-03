<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Shift;
use App\Models\User;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ShiftBulkEditModal extends Component
{
    public $isOpen = false;
    public $selectedDates = [];
    public $staffShifts = [];
    public $storeId;
    public $currentMonth;
    public $currentYear;
    
    public function mount()
    {
        $this->currentMonth = now()->month;
        $this->currentYear = now()->year;
        $this->storeId = Store::first()->id ?? 1;
        $this->loadStaffList();
    }
    
    public function openModal()
    {
        $this->isOpen = true;
        $this->selectedDates = [];
        $this->resetStaffShifts();
    }
    
    public function closeModal()
    {
        $this->isOpen = false;
    }
    
    public function loadStaffList()
    {
        $staff = User::where('is_active_staff', true)->get();
        
        foreach ($staff as $user) {
            $this->staffShifts[$user->id] = [
                'name' => $user->name,
                'enabled' => false,
                'pattern' => '',
                'start_time' => '10:00',
                'end_time' => '20:00',
            ];
        }
    }
    
    public function resetStaffShifts()
    {
        foreach ($this->staffShifts as $userId => &$shift) {
            $shift['enabled'] = false;
            $shift['pattern'] = '';
            $shift['start_time'] = '10:00';
            $shift['end_time'] = '20:00';
        }
    }
    
    public function selectWeekdays()
    {
        $this->selectedDates = [];
        $start = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $end = $start->copy()->endOfMonth();
        
        while ($start <= $end) {
            if ($start->isWeekday()) {
                $this->selectedDates[] = $start->format('Y-m-d');
            }
            $start->addDay();
        }
    }
    
    public function selectWeekends()
    {
        $this->selectedDates = [];
        $start = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $end = $start->copy()->endOfMonth();
        
        while ($start <= $end) {
            if ($start->isWeekend()) {
                $this->selectedDates[] = $start->format('Y-m-d');
            }
            $start->addDay();
        }
    }
    
    public function selectAll()
    {
        $this->selectedDates = [];
        $start = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $end = $start->copy()->endOfMonth();
        
        while ($start <= $end) {
            $this->selectedDates[] = $start->format('Y-m-d');
            $start->addDay();
        }
    }
    
    public function clearSelection()
    {
        $this->selectedDates = [];
    }
    
    public function updatePattern($userId, $pattern)
    {
        $this->staffShifts[$userId]['pattern'] = $pattern;
        
        switch($pattern) {
            case 'full':
                $this->staffShifts[$userId]['start_time'] = '10:00';
                $this->staffShifts[$userId]['end_time'] = '20:00';
                break;
            case 'morning':
                $this->staffShifts[$userId]['start_time'] = '10:00';
                $this->staffShifts[$userId]['end_time'] = '15:00';
                break;
            case 'afternoon':
                $this->staffShifts[$userId]['start_time'] = '13:00';
                $this->staffShifts[$userId]['end_time'] = '20:00';
                break;
        }
    }
    
    public function save()
    {
        if (empty($this->selectedDates)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '日付を選択してください',
            ]);
            return;
        }
        
        DB::transaction(function() {
            foreach ($this->selectedDates as $date) {
                foreach ($this->staffShifts as $userId => $shiftData) {
                    if (!$shiftData['enabled']) continue;
                    
                    $existing = Shift::where('shift_date', $date)
                        ->where('user_id', $userId)
                        ->where('store_id', $this->storeId)
                        ->first();
                    
                    $data = [
                        'store_id' => $this->storeId,
                        'user_id' => $userId,
                        'shift_date' => $date,
                        'start_time' => $shiftData['start_time'],
                        'end_time' => $shiftData['end_time'],
                        'status' => 'scheduled',
                        'is_available_for_reservation' => true,
                    ];
                    
                    // 休憩時間の自動設定
                    $hours = Carbon::parse($shiftData['start_time'])->diffInHours(Carbon::parse($shiftData['end_time']));
                    if ($hours >= 8) {
                        $breakStart = Carbon::parse($shiftData['start_time'])->addHours(4);
                        $data['break_start'] = $breakStart->format('H:i:s');
                        $data['break_end'] = $breakStart->addMinutes(60)->format('H:i:s');
                    } elseif ($hours >= 6) {
                        $breakStart = Carbon::parse($shiftData['start_time'])->addHours(3);
                        $data['break_start'] = $breakStart->format('H:i:s');
                        $data['break_end'] = $breakStart->addMinutes(45)->format('H:i:s');
                    }
                    
                    if ($existing) {
                        $existing->update($data);
                    } else {
                        Shift::create($data);
                    }
                }
            }
        });
        
        $this->dispatch('shift-updated');
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'シフトを一括登録しました',
        ]);
        $this->closeModal();
    }
    
    public function render()
    {
        $daysInMonth = [];
        $start = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $end = $start->copy()->endOfMonth();
        
        while ($start <= $end) {
            $daysInMonth[] = [
                'date' => $start->format('Y-m-d'),
                'label' => $start->format('n/j'),
                'dayOfWeek' => ['日', '月', '火', '水', '木', '金', '土'][$start->dayOfWeek],
            ];
            $start->addDay();
        }
        
        return view('livewire.shift-bulk-edit-modal', [
            'daysInMonth' => $daysInMonth,
        ]);
    }
}