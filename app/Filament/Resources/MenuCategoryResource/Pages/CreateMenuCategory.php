<?php

namespace App\Filament\Resources\MenuCategoryResource\Pages;

use App\Filament\Resources\MenuCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMenuCategory extends CreateRecord
{
    protected static string $resource = MenuCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        // スーパーアドミン以外は、自動的に所属店舗のIDを設定
        if (!$user->hasRole('super_admin')) {
            $data['store_id'] = $user->store_id;
        }

        return $data;
    }
}
