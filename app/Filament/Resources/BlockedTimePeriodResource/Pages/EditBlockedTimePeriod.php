<?php

namespace App\Filament\Resources\BlockedTimePeriodResource\Pages;

use App\Filament\Resources\BlockedTimePeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlockedTimePeriod extends EditRecord
{
    protected static string $resource = BlockedTimePeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(function () {
                    $user = auth()->user();
                    $record = $this->record;
                    
                    // スーパーアドミンとオーナーは常に削除可能
                    if ($user->hasRole(['super_admin', 'owner'])) {
                        return true;
                    }
                    
                    // スタッフと店長は未来の予約ブロックのみ削除可能
                    if ($user->hasRole(['staff', 'manager'])) {
                        return $record->blocked_date->isFuture() || $record->blocked_date->isToday();
                    }
                    
                    return false;
                }),
        ];
    }
    
    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();
        
        $user = auth()->user();
        $record = $this->record;
        
        // スタッフと店長は過去の予約ブロックを編集できない
        if ($user->hasRole(['staff', 'manager'])) {
            if ($record->blocked_date->isPast() && !$record->blocked_date->isToday()) {
                abort(403, '過去の予約ブロックは編集できません。');
            }
        }
    }
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();
        
        // スタッフ・店長の場合、store_idを自店舗に固定
        if ($user->hasRole(['staff', 'manager']) && $user->store_id) {
            $data['store_id'] = $user->store_id;
        }
        
        // 終日の場合の時刻設定
        if (!empty($data['is_all_day'])) {
            $data['start_time'] = '00:00:00';
            $data['end_time'] = '23:59:59';
        }
        
        return $data;
    }
}