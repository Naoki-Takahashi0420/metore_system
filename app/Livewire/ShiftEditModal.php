<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;

class ShiftEditModal extends Component
{
    public $isOpen = false;
    public $selectedDate;
    public $storeId;
    public $staffShifts = [];
    
    protected $listeners = ['open-shift-modal' => 'openModal'];
    
    public function mount()
    {
        $this->loadStaffList();
    }
    
    public function openModal($date)
    {
        $this->selectedDate = $date;
        $this->loadShifts();
        $this->isOpen = true;
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
                'start_time' => '',
                'end_time' => '',
                'is_holiday' => false,
            ];
        }
    }
    
    public function loadShifts()
    {
        if (!$this->selectedDate) return;
        
        $shifts = Shift::where('shift_date', $this->selectedDate)
            ->where('store_id', $this->storeId ?? 1)
            ->get();
        
        foreach ($shifts as $shift) {
            if (isset($this->staffShifts[$shift->user_id])) {
                $this->staffShifts[$shift->user_id]['start_time'] = Carbon::parse($shift->start_time)->format('H:i');
                $this->staffShifts[$shift->user_id]['end_time'] = Carbon::parse($shift->end_time)->format('H:i');
                $this->staffShifts[$shift->user_id]['is_holiday'] = false;
            }
        }
    }
    
    public function save()
    {
        foreach ($this->staffShifts as $userId => $shiftData) {
            $existing = Shift::where('shift_date', $this->selectedDate)
                ->where('user_id', $userId)
                ->where('store_id', $this->storeId ?? 1)
                ->first();
            
            if ($shiftData['is_holiday'] || (!$shiftData['start_time'] && !$shiftData['end_time'])) {
                // 休み or 未入力の場合は削除
                if ($existing) {
                    $existing->delete();
                }
            } else if ($shiftData['start_time'] && $shiftData['end_time']) {
                // シフトがある場合
                $data = [
                    'store_id' => $this->storeId ?? 1,
                    'user_id' => $userId,
                    'shift_date' => $this->selectedDate,
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
        
        $this->dispatch('shift-updated');
        $this->closeModal();
    }
    
    public function render()
    {
        return view('livewire.shift-edit-modal');
    }
}