<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\ShiftPattern;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

class ShiftPatternModal extends Component
{
    public $isOpen = false;
    public $patterns = [];
    public $editingPattern = null;
    public $newPattern = [
        'name' => '',
        'store_id' => null,
        'start_time' => '10:00',
        'end_time' => '20:00',
        'break_start' => null,
        'break_end' => null,
        'days_of_week' => [],
        'color' => '#3B82F6',
    ];
    
    public function mount()
    {
        $this->newPattern['store_id'] = Store::first()->id ?? 1;
        $this->loadPatterns();
    }
    
    public function openModal()
    {
        $this->isOpen = true;
        $this->loadPatterns();
        $this->resetNewPattern();
    }
    
    public function closeModal()
    {
        $this->isOpen = false;
        $this->editingPattern = null;
    }
    
    public function loadPatterns()
    {
        $this->patterns = ShiftPattern::with('store')
            ->orderBy('name')
            ->get()
            ->toArray();
    }
    
    public function resetNewPattern()
    {
        $this->newPattern = [
            'name' => '',
            'store_id' => Store::first()->id ?? 1,
            'start_time' => '10:00',
            'end_time' => '20:00',
            'break_start' => null,
            'break_end' => null,
            'days_of_week' => [],
            'color' => '#3B82F6',
        ];
    }
    
    public function createPattern()
    {
        $this->validate([
            'newPattern.name' => 'required|string|max:255',
            'newPattern.start_time' => 'required',
            'newPattern.end_time' => 'required|after:newPattern.start_time',
        ]);
        
        // 自動休憩時間設定
        $hours = \Carbon\Carbon::parse($this->newPattern['start_time'])
            ->diffInHours(\Carbon\Carbon::parse($this->newPattern['end_time']));
        
        if ($hours >= 8 && !$this->newPattern['break_start']) {
            $breakStart = \Carbon\Carbon::parse($this->newPattern['start_time'])->addHours(4);
            $this->newPattern['break_start'] = $breakStart->format('H:i');
            $this->newPattern['break_end'] = $breakStart->addMinutes(60)->format('H:i');
        } elseif ($hours >= 6 && !$this->newPattern['break_start']) {
            $breakStart = \Carbon\Carbon::parse($this->newPattern['start_time'])->addHours(3);
            $this->newPattern['break_start'] = $breakStart->format('H:i');
            $this->newPattern['break_end'] = $breakStart->addMinutes(45)->format('H:i');
        }
        
        $this->newPattern['days_of_week'] = !empty($this->newPattern['days_of_week']) 
            ? $this->newPattern['days_of_week'] 
            : [];
        
        ShiftPattern::create($this->newPattern);
        
        $this->loadPatterns();
        $this->resetNewPattern();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'パターンを作成しました',
        ]);
    }
    
    public function editPattern($patternId)
    {
        $pattern = ShiftPattern::find($patternId);
        if ($pattern) {
            $this->editingPattern = $pattern->toArray();
            $this->editingPattern['days_of_week'] = $this->editingPattern['days_of_week'] ?? [];
        }
    }
    
    public function updatePattern()
    {
        $this->validate([
            'editingPattern.name' => 'required|string|max:255',
            'editingPattern.start_time' => 'required',
            'editingPattern.end_time' => 'required|after:editingPattern.start_time',
        ]);
        
        $pattern = ShiftPattern::find($this->editingPattern['id']);
        if ($pattern) {
            $pattern->update($this->editingPattern);
            $this->loadPatterns();
            $this->editingPattern = null;
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'パターンを更新しました',
            ]);
        }
    }
    
    public function deletePattern($patternId)
    {
        $pattern = ShiftPattern::find($patternId);
        if ($pattern) {
            $pattern->delete();
            $this->loadPatterns();
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'パターンを削除しました',
            ]);
        }
    }
    
    public function cancelEdit()
    {
        $this->editingPattern = null;
    }
    
    public function render()
    {
        return view('livewire.shift-pattern-modal');
    }
}