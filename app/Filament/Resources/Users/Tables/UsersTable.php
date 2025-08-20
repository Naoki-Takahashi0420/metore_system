<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('store.name')
                    ->label('店舗')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->label('名前')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('メールアドレス')
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->label('メール確認日時')
                    ->dateTime('Y年m月d日 H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('role')
                    ->label('役割')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'admin' => '管理者',
                        'manager' => 'マネージャー',
                        'staff' => 'スタッフ',
                        'customer' => '顧客',
                        default => $state
                    })
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'admin' => 'danger',
                        'manager' => 'warning',
                        'staff' => 'info',
                        'customer' => 'success',
                        default => 'gray'
                    })
                    ->searchable(),
                TextColumn::make('hourly_rate')
                    ->label('時給')
                    ->numeric()
                    ->suffix('円')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('アクティブ')
                    ->boolean(),
                TextColumn::make('last_login_at')
                    ->label('最終ログイン')
                    ->dateTime('Y年m月d日 H:i')
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
                SelectFilter::make('role')
                    ->label('役割')
                    ->options([
                        'admin' => '管理者',
                        'manager' => 'マネージャー',
                        'staff' => 'スタッフ',
                        'customer' => '顧客',
                    ]),
                SelectFilter::make('is_active')
                    ->label('アクティブ')
                    ->options([
                        '1' => 'アクティブ',
                        '0' => '非アクティブ',
                    ]),
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
