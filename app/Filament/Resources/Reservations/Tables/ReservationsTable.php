<?php

namespace App\Filament\Resources\Reservations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reservation_number')
                    ->label('予約番号')
                    ->searchable(),
                TextColumn::make('store.name')
                    ->label('店舗')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('顧客')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('staff.name')
                    ->label('スタッフ')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reservation_date')
                    ->label('予約日')
                    ->date('Y年m月d日')
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('開始時刻')
                    ->time('H:i')
                    ->sortable(),
                TextColumn::make('end_time')
                    ->label('終了時刻')
                    ->time('H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('ステータス')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'pending' => '保留中',
                        'confirmed' => '確定',
                        'completed' => '完了',
                        'cancelled' => 'キャンセル',
                        default => $state
                    })
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'pending' => 'warning',
                        'confirmed' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray'
                    })
                    ->searchable(),
                TextColumn::make('guest_count')
                    ->label('人数')
                    ->numeric()
                    ->suffix('名')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('合計金額')
                    ->numeric()
                    ->suffix('円')
                    ->sortable(),
                TextColumn::make('deposit_amount')
                    ->label('保証金')
                    ->numeric()
                    ->suffix('円')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_method')
                    ->label('支払い方法')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'cash' => '現金',
                        'card' => 'クレジットカード',
                        'transfer' => '銀行振込',
                        default => $state
                    })
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_status')
                    ->label('支払い状態')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'unpaid' => '未支払い',
                        'partial' => '一部支払い',
                        'paid' => '支払い済み',
                        'refunded' => '返金済み',
                        default => $state
                    })
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'unpaid' => 'danger',
                        'partial' => 'warning',
                        'paid' => 'success',
                        'refunded' => 'info',
                        default => 'gray'
                    })
                    ->searchable(),
                TextColumn::make('confirmed_at')
                    ->label('確定日時')
                    ->dateTime('Y年m月d日 H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cancelled_at')
                    ->label('キャンセル日時')
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
                SelectFilter::make('status')
                    ->label('ステータス')
                    ->options([
                        'pending' => '保留中',
                        'confirmed' => '確定',
                        'completed' => '完了',
                        'cancelled' => 'キャンセル',
                    ]),
                SelectFilter::make('payment_status')
                    ->label('支払い状態')
                    ->options([
                        'unpaid' => '未支払い',
                        'partial' => '一部支払い',
                        'paid' => '支払い済み',
                        'refunded' => '返金済み',
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
