<?php

namespace App\Filament\Resources\Stores\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('店舗名')
                    ->searchable(),
                TextColumn::make('name_kana')
                    ->label('店舗名（カナ）')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('prefecture')
                    ->label('都道府県')
                    ->searchable(),
                TextColumn::make('city')
                    ->label('市区町村')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('住所')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->label('電話番号')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('メールアドレス')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('capacity')
                    ->label('定員')
                    ->numeric()
                    ->suffix('名')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('作成日')
                    ->dateTime('Y年m月d日')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('更新日')
                    ->dateTime('Y年m月d日')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->label('編集'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('削除'),
                ]),
            ]);
    }
}
