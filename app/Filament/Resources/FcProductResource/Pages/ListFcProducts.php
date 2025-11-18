<?php

namespace App\Filament\Resources\FcProductResource\Pages;

use App\Filament\Resources\FcProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFcProducts extends ListRecords
{
    protected static string $resource = FcProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
