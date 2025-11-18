<?php

namespace App\Filament\Resources\FcProductCategoryResource\Pages;

use App\Filament\Resources\FcProductCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFcProductCategory extends EditRecord
{
    protected static string $resource = FcProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
