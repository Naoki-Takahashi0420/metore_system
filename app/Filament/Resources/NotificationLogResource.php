<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationLogResource\Pages;
use App\Filament\Resources\NotificationLogResource\RelationManagers;
use App\Models\NotificationLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class NotificationLogResource extends Resource
{
    protected static ?string $model = NotificationLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationLabel = '通知履歴';

    protected static ?string $modelLabel = '通知ログ';

    protected static ?string $pluralModelLabel = '通知履歴';

    protected static ?int $navigationSort = 100;

    protected static bool $shouldRegisterNavigation = false; // ナビゲーションに表示しない（System Logsページから統合）

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\Select::make('reservation_id')
                            ->label('予約ID')
                            ->relationship('reservation', 'reservation_number')
                            ->disabled(),
                        Forms\Components\Select::make('customer_id')
                            ->label('顧客')
                            ->relationship('customer', 'last_name')
                            ->disabled(),
                        Forms\Components\Select::make('store_id')
                            ->label('店舗')
                            ->relationship('store', 'name')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('通知情報')
                    ->schema([
                        Forms\Components\TextInput::make('notification_type')
                            ->label('通知種別')
                            ->disabled(),
                        Forms\Components\TextInput::make('channel')
                            ->label('チャネル')
                            ->disabled(),
                        Forms\Components\TextInput::make('status')
                            ->label('ステータス')
                            ->disabled(),
                        Forms\Components\TextInput::make('recipient')
                            ->label('送信先')
                            ->disabled(),
                        Forms\Components\TextInput::make('message_id')
                            ->label('メッセージID')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('sent_at')
                            ->label('送信日時')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('エラー情報')
                    ->schema([
                        Forms\Components\TextInput::make('error_code')
                            ->label('エラーコード')
                            ->disabled(),
                        Forms\Components\Textarea::make('error_message')
                            ->label('エラーメッセージ')
                            ->disabled()
                            ->columnSpanFull(),
                    ])->hidden(fn ($record) => $record && $record->status === 'sent'),

                Forms\Components\Section::make('追加情報')
                    ->schema([
                        Forms\Components\Textarea::make('metadata')
                            ->label('メタデータ')
                            ->disabled()
                            ->columnSpanFull()
                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
                    ])->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日時')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->default(true),
                Tables\Columns\BadgeColumn::make('channel')
                    ->label('チャネル')
                    ->colors([
                        'primary' => 'line',
                        'warning' => 'sms',
                        'info' => 'email',
                    ])
                    ->icons([
                        'heroicon-o-chat-bubble-left-ellipsis' => 'line',
                        'heroicon-o-device-phone-mobile' => 'sms',
                        'heroicon-o-envelope' => 'email',
                    ])
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'line' => 'LINE',
                        'sms' => 'SMS',
                        'email' => 'メール',
                        default => $state,
                    }),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('ステータス')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'sent',
                        'danger' => 'failed',
                    ])
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'pending' => '送信中',
                        'sent' => '成功',
                        'failed' => '失敗',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('notification_type')
                    ->label('通知種別')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'reservation_confirmation' => '予約確認',
                        'reservation_change' => '予約変更',
                        'reservation_cancellation' => '予約キャンセル',
                        'reservation_reminder' => '予約リマインダー',
                        'follow_up' => 'フォローアップ',
                        default => $state,
                    })
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('customer.last_name')
                    ->label('顧客名')
                    ->formatStateUsing(fn ($record) => $record->customer
                        ? $record->customer->last_name . ' ' . $record->customer->first_name
                        : '-')
                    ->searchable(['customers.last_name', 'customers.first_name'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('店舗')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('recipient')
                    ->label('送信先')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('error_code')
                    ->label('エラーコード')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('送信日時')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->label('チャネル')
                    ->options([
                        'line' => 'LINE',
                        'sms' => 'SMS',
                        'email' => 'メール',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('ステータス')
                    ->options([
                        'pending' => '送信中',
                        'sent' => '成功',
                        'failed' => '失敗',
                    ]),
                Tables\Filters\SelectFilter::make('notification_type')
                    ->label('通知種別')
                    ->options([
                        'reservation_confirmation' => '予約確認',
                        'reservation_change' => '予約変更',
                        'reservation_cancellation' => '予約キャンセル',
                        'reservation_reminder' => '予約リマインダー',
                        'follow_up' => 'フォローアップ',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('作成日（開始）'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('作成日（終了）'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // 読み取り専用のため、削除アクションは無効化
            ]);
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
            'index' => Pages\ListNotificationLogs::route('/'),
            'view' => Pages\ViewNotificationLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
