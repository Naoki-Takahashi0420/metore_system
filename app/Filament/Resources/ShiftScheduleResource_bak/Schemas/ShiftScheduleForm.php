<?php

namespace App\Filament\Resources\ShiftSchedules\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class ShiftScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('store_id')
                    ->required()
                    ->numeric(),
                TextInput::make('staff_id')
                    ->required()
                    ->numeric(),
                DatePicker::make('shift_date')
                    ->required(),
                TimePicker::make('start_time')
                    ->required(),
                TimePicker::make('end_time')
                    ->required(),
                TimePicker::make('break_start'),
                TimePicker::make('break_end'),
                TextInput::make('status')
                    ->required()
                    ->default('scheduled'),
                TimePicker::make('actual_start'),
                TimePicker::make('actual_end'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
