<?php

namespace App\Filament\Resources\Menus\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MenusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('store.name')
                    ->label('店舗')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label('カテゴリー')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('メニュー名')
                    ->searchable(),
                TextColumn::make('price')
                    ->label('料金')
                    ->numeric()
                    ->suffix('円')
                    ->sortable(),
                TextColumn::make('duration')
                    ->label('所要時間')
                    ->numeric()
                    ->suffix('分')
                    ->sortable(),
                IconColumn::make('is_available')
                    ->label('提供中')
                    ->boolean(),
                TextColumn::make('max_daily_quantity')
                    ->label('一日の上限数')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sort_order')
                    ->label('表示順')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
