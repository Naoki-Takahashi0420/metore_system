<?php

namespace App\Filament\Resources\HelpChatLogResource\Pages;

use App\Filament\Resources\HelpChatLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHelpChatLogs extends ListRecords
{
    protected static string $resource = HelpChatLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
