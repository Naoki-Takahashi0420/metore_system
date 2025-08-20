<?php

namespace App\Filament\Resources\CustomerResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('氏名')
                    ->searchable(['last_name', 'first_name', 'last_name_kana', 'first_name_kana']),
                TextColumn::make('last_name')
                    ->label('姓')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('first_name')
                    ->label('名')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_name_kana')
                    ->label('姓（カナ）')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('first_name_kana')
                    ->label('名（カナ）')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->label('電話番号')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('メールアドレス')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('生年月日')
                    ->date('Y年m月d日')
                    ->sortable(),
                TextColumn::make('gender')
                    ->label('性別')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'male' => '男性',
                        'female' => '女性',
                        'other' => 'その他',
                        default => '-',
                    })
                    ->searchable(),
                TextColumn::make('postal_code')
                    ->label('郵便番号')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_blocked')
                    ->label('ブロック')
                    ->boolean(),
                TextColumn::make('last_visit_at')
                    ->label('最終来店日')
                    ->dateTime('Y年m月d日')
                    ->sortable(),
                TextColumn::make('phone_verified_at')
                    ->label('電話認証日')
                    ->dateTime('Y年m月d日')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('登録日')
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
