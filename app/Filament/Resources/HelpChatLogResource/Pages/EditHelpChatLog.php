<?php

namespace App\Filament\Resources\HelpChatLogResource\Pages;

use App\Filament\Resources\HelpChatLogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHelpChatLog extends EditRecord
{
    protected static string $resource = HelpChatLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
