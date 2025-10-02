<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // 既存のmanageable_storesをロード
        $data['manageable_stores'] = $this->record->manageableStores()->pluck('stores.id')->toArray();

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        
        // 既存の管理店舗関係をクリア
        $record->manageableStores()->detach();
        
        // オーナーのみ管理可能店舗の関係を再保存
        if ($record->hasRole('owner') && isset($this->data['manageable_stores'])) {
            $storeIds = $this->data['manageable_stores'];
            
            foreach ($storeIds as $storeId) {
                $record->manageableStores()->attach($storeId, ['role' => 'owner']);
            }
        }
    }
}
