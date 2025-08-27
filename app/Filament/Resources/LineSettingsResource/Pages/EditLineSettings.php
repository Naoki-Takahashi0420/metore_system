<?php

namespace App\Filament\Resources\LineSettingsResource\Pages;

use App\Filament\Resources\LineSettingsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLineSettings extends EditRecord
{
    protected static string $resource = LineSettingsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
