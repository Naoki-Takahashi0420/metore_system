<?php

namespace App\Filament\Resources\MedicalRecords\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MedicalRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('customer_id')
                    ->required()
                    ->numeric(),
                TextInput::make('staff_id')
                    ->required()
                    ->numeric(),
                TextInput::make('reservation_id')
                    ->numeric(),
                DatePicker::make('visit_date')
                    ->required(),
                Textarea::make('symptoms')
                    ->columnSpanFull(),
                Textarea::make('diagnosis')
                    ->columnSpanFull(),
                Textarea::make('treatment')
                    ->columnSpanFull(),
                Textarea::make('medications')
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->columnSpanFull(),
                DatePicker::make('next_visit_date'),
            ]);
    }
}
