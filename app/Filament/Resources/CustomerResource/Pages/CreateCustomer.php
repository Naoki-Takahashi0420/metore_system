<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        // スタッフ・店長の場合、store_idが未設定なら自店舗を自動設定
        if ($user->hasRole(['staff', 'manager']) && $user->store_id) {
            if (empty($data['store_id'])) {
                $data['store_id'] = $user->store_id;
            }
        }

        return $data;
    }
}
