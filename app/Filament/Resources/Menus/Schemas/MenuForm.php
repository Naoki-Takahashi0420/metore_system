<?php

namespace App\Filament\Resources\Menus\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MenuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('store_id')
                    ->label('店舗')
                    ->relationship('store', 'name')
                    ->required(),
                TextInput::make('category')
                    ->label('カテゴリー'),
                TextInput::make('name')
                    ->label('メニュー名')
                    ->required(),
                Textarea::make('description')
                    ->label('説明')
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->label('料金')
                    ->required()
                    ->numeric()
                    ->suffix('円'),
                TextInput::make('duration')
                    ->label('所要時間')
                    ->required()
                    ->numeric()
                    ->suffix('分'),
                Toggle::make('is_available')
                    ->label('提供中')
                    ->default(true),
                TextInput::make('max_daily_quantity')
                    ->label('一日の上限数')
                    ->numeric(),
                TextInput::make('sort_order')
                    ->label('表示順')
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('options')
                    ->label('オプション')
                    ->columnSpanFull(),
                Textarea::make('tags')
                    ->label('タグ')
                    ->columnSpanFull(),
            ]);
    }
}
