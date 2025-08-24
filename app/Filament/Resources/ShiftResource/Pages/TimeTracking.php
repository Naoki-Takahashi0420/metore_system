<?php

namespace App\Filament\Resources\ShiftResource\Pages;

use App\Filament\Resources\ShiftResource;
use App\Models\Shift;
use Filament\Resources\Pages\Page;

class TimeTracking extends Page
{
    protected static string $resource = ShiftResource::class;

    protected static string $view = 'filament.resources.shift-resource.pages.time-tracking';
    
    protected static ?string $title = '勤怠入力';
    
    public function clockIn($shiftId)
    {
        $shift = Shift::find($shiftId);
        $user = auth()->user();
        
        if (!$shift) {
            $this->addError('error', 'シフトが見つかりません');
            return;
        }
        
        if ($user->id !== $shift->user_id && $user->role !== 'superadmin') {
            $this->addError('error', '権限がありません');
            return;
        }
        
        if ($shift->actual_start_time) {
            $this->addError('error', 'すでに出勤済みです');
            return;
        }
        
        $shift->update([
            'actual_start_time' => now()->format('H:i:s'),
            'status' => 'working'
        ]);
        
        \Filament\Notifications\Notification::make()
            ->success()
            ->title('出勤完了')
            ->body($shift->user->name . 'さんが出勤しました (' . now()->format('H:i') . ')')
            ->send();
    }
    
    public function startBreak($shiftId)
    {
        $shift = Shift::find($shiftId);
        $user = auth()->user();
        
        if (!$shift) {
            $this->addError('error', 'シフトが見つかりません');
            return;
        }
        
        if ($user->id !== $shift->user_id && $user->role !== 'superadmin') {
            $this->addError('error', '権限がありません');
            return;
        }
        
        if (!$shift->actual_start_time) {
            $this->addError('error', '出勤してから休憩を開始してください');
            return;
        }
        
        if ($shift->actual_break_start) {
            $this->addError('error', 'すでに休憩中です');
            return;
        }
        
        $shift->update([
            'actual_break_start' => now()->format('H:i:s')
        ]);
        
        \Filament\Notifications\Notification::make()
            ->success()
            ->title('休憩開始')
            ->body($shift->user->name . 'さんが休憩に入りました (' . now()->format('H:i') . ')')
            ->send();
    }
    
    public function endBreak($shiftId)
    {
        $shift = Shift::find($shiftId);
        $user = auth()->user();
        
        if (!$shift) {
            $this->addError('error', 'シフトが見つかりません');
            return;
        }
        
        if ($user->id !== $shift->user_id && $user->role !== 'superadmin') {
            $this->addError('error', '権限がありません');
            return;
        }
        
        if (!$shift->actual_break_start) {
            $this->addError('error', '休憩を開始してから終了してください');
            return;
        }
        
        if ($shift->actual_break_end) {
            $this->addError('error', 'すでに休憩終了済みです');
            return;
        }
        
        $shift->update([
            'actual_break_end' => now()->format('H:i:s')
        ]);
        
        \Filament\Notifications\Notification::make()
            ->success()
            ->title('休憩終了')
            ->body($shift->user->name . 'さんが休憩から戻りました (' . now()->format('H:i') . ')')
            ->send();
    }
    
    public function clockOut($shiftId)
    {
        $shift = Shift::find($shiftId);
        $user = auth()->user();
        
        if (!$shift) {
            $this->addError('error', 'シフトが見つかりません');
            return;
        }
        
        if ($user->id !== $shift->user_id && $user->role !== 'superadmin') {
            $this->addError('error', '権限がありません');
            return;
        }
        
        if (!$shift->actual_start_time) {
            $this->addError('error', '出勤してから退勤してください');
            return;
        }
        
        if ($shift->actual_end_time) {
            $this->addError('error', 'すでに退勤済みです');
            return;
        }
        
        $shift->update([
            'actual_end_time' => now()->format('H:i:s'),
            'status' => 'completed'
        ]);
        
        \Filament\Notifications\Notification::make()
            ->success()
            ->title('退勤完了')
            ->body($shift->user->name . 'さんが退勤しました (' . now()->format('H:i') . ')')
            ->send();
    }
    
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('list')
                ->label('シフト一覧')
                ->icon('heroicon-o-list-bullet')
                ->url(ShiftResource::getUrl('index'))
                ->color('gray'),
        ];
    }
}