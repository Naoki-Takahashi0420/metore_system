<?php

namespace App\Filament\Resources\Reservations\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class ReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('reservation_number')
                    ->required(),
                TextInput::make('store_id')
                    ->required()
                    ->numeric(),
                TextInput::make('customer_id')
                    ->required()
                    ->numeric(),
                TextInput::make('staff_id')
                    ->numeric(),
                DatePicker::make('reservation_date')
                    ->required(),
                TimePicker::make('start_time')
                    ->required(),
                TimePicker::make('end_time')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                TextInput::make('guest_count')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('total_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('deposit_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('payment_method'),
                TextInput::make('payment_status')
                    ->required()
                    ->default('unpaid'),
                Textarea::make('menu_items')
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->columnSpanFull(),
                Textarea::make('cancel_reason')
                    ->columnSpanFull(),
                DateTimePicker::make('confirmed_at'),
                DateTimePicker::make('cancelled_at'),
            ]);
    }
}
