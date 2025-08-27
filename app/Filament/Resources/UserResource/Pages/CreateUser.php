<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;
        
        // オーナーのみ管理可能店舗の関係を保存
        if ($record->hasRole('owner') && isset($this->data['manageable_stores'])) {
            $storeIds = $this->data['manageable_stores'];
            
            foreach ($storeIds as $storeId) {
                $record->manageableStores()->attach($storeId, ['role' => 'owner']);
            }
        }
    }
}
