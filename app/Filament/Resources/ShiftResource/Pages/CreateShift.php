<?php

namespace App\Filament\Resources\ShiftResource\Pages;

use App\Filament\Resources\ShiftResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateShift extends CreateRecord
{
    protected static string $resource = ShiftResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('シフト登録完了')
            ->body('シフトが正常に登録されました。');
    }
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 自動でステータスを「予定」に設定
        if (!isset($data['status'])) {
            $data['status'] = 'scheduled';
        }
        
        return $data;
    }
}