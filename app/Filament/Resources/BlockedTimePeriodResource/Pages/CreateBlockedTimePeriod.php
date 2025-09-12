<?php

namespace App\Filament\Resources\BlockedTimePeriodResource\Pages;

use App\Filament\Resources\BlockedTimePeriodResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlockedTimePeriod extends CreateRecord
{
    protected static string $resource = BlockedTimePeriodResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        
        // スタッフ・店長の場合、store_idが未設定なら自店舗を設定
        if ($user->hasRole(['staff', 'manager']) && $user->store_id) {
            if (empty($data['store_id'])) {
                $data['store_id'] = $user->store_id;
            }
        }
        
        // 終日の場合の時刻設定
        if (!empty($data['is_all_day'])) {
            $data['start_time'] = '00:00:00';
            $data['end_time'] = '23:59:59';
        }
        
        return $data;
    }
}