<?php

namespace App\Filament\Resources\LineMessageTemplateResource\Pages;

use App\Filament\Resources\LineMessageTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLineMessageTemplates extends ListRecords
{
    protected static string $resource = LineMessageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
