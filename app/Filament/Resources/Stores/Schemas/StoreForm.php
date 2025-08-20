<?php

namespace App\Filament\Resources\Stores\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('店舗名')
                    ->required(),
                TextInput::make('name_kana')
                    ->label('店舗名（カナ）'),
                TextInput::make('postal_code')
                    ->label('郵便番号'),
                TextInput::make('prefecture')
                    ->label('都道府県')
                    ->required(),
                TextInput::make('city')
                    ->label('市区町村')
                    ->required(),
                TextInput::make('address')
                    ->label('住所')
                    ->required(),
                TextInput::make('phone')
                    ->label('電話番号')
                    ->tel()
                    ->required(),
                TextInput::make('email')
                    ->label('メールアドレス')
                    ->email(),
                Textarea::make('opening_hours')
                    ->label('営業時間')
                    ->columnSpanFull(),
                Textarea::make('holidays')
                    ->label('休業日')
                    ->columnSpanFull(),
                TextInput::make('capacity')
                    ->label('定員')
                    ->required()
                    ->numeric()
                    ->default(10),
                Textarea::make('settings')
                    ->label('設定')
                    ->columnSpanFull(),
                Textarea::make('reservation_settings')
                    ->label('予約設定')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('有効')
                    ->default(true),
            ]);
    }
}
