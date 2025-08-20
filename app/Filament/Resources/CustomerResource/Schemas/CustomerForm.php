<?php

namespace App\Filament\Resources\CustomerResource\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('last_name')
                    ->label('姓')
                    ->required(),
                TextInput::make('first_name')
                    ->label('名')
                    ->required(),
                TextInput::make('last_name_kana')
                    ->label('姓（カナ）'),
                TextInput::make('first_name_kana')
                    ->label('名（カナ）'),
                TextInput::make('phone')
                    ->label('電話番号')
                    ->tel()
                    ->required(),
                TextInput::make('email')
                    ->label('メールアドレス')
                    ->email(),
                DatePicker::make('birth_date')
                    ->label('生年月日'),
                Select::make('gender')
                    ->label('性別')
                    ->options([
                        'male' => '男性',
                        'female' => '女性',
                        'other' => 'その他',
                    ]),
                TextInput::make('postal_code')
                    ->label('郵便番号'),
                Textarea::make('address')
                    ->label('住所')
                    ->columnSpanFull(),
                Textarea::make('preferences')
                    ->label('希望・好み')
                    ->columnSpanFull(),
                Textarea::make('medical_notes')
                    ->label('医療メモ')
                    ->columnSpanFull(),
                Toggle::make('is_blocked')
                    ->label('ブロック')
                    ->default(false),
                DateTimePicker::make('last_visit_at')
                    ->label('最終来店日'),
                DateTimePicker::make('phone_verified_at')
                    ->label('電話認証日'),
            ]);
    }
}
