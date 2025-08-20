<?php

namespace App\Filament\Resources\ShiftSchedules;

use App\Filament\Resources\ShiftSchedules\Pages\CreateShiftSchedule;
use App\Filament\Resources\ShiftSchedules\Pages\EditShiftSchedule;
use App\Filament\Resources\ShiftSchedules\Pages\ListShiftSchedules;
use App\Filament\Resources\ShiftSchedules\Schemas\ShiftScheduleForm;
use App\Filament\Resources\ShiftSchedules\Tables\ShiftSchedulesTable;
use App\Models\ShiftSchedule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ShiftScheduleResource extends Resource
{
    protected static ?string $model = ShiftSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ShiftScheduleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShiftSchedulesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShiftSchedules::route('/'),
            'create' => CreateShiftSchedule::route('/create'),
            'edit' => EditShiftSchedule::route('/{record}/edit'),
        ];
    }
}
