<?php

namespace App\Filament\Resources\FcProductCategoryResource\Pages;

use App\Filament\Resources\FcProductCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFcProductCategories extends ListRecords
{
    protected static string $resource = FcProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
