<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservationLineResource\Pages;
use App\Models\ReservationLine;
use App\Models\Store;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReservationLineResource extends Resource
{
    protected static ?string $model = ReservationLine::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';
    
    protected static ?string $navigationLabel = '予約ライン管理';
    
    protected static ?string $modelLabel = '予約ライン';
    
    protected static ?string $pluralModelLabel = '予約ライン';
    
    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本設定')
                    ->schema([
                        Forms\Components\Select::make('store_id')
                            ->label('店舗')
                            ->options(Store::pluck('name', 'id'))
                            ->required()
                            ->reactive(),
                        
                        Forms\Components\TextInput::make('line_name')
                            ->label('ライン名')
                            ->required()
                            ->placeholder('例：本ライン1、予備ライン1'),
                        
                        Forms\Components\Select::make('line_type')
                            ->label('ライン種別')
                            ->options([
                                'main' => '本ライン（メイン）',
                                'sub' => '予備ライン（サブ）',
                            ])
                            ->required()
                            ->reactive(),
                        
                        Forms\Components\TextInput::make('line_number')
                            ->label('ライン番号')
                            ->numeric()
                            ->required()
                            ->default(1),
                        
                        Forms\Components\TextInput::make('capacity')
                            ->label('同時施術可能数')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->helperText('同時に何名まで施術可能か'),
                        
                        Forms\Components\TextInput::make('priority')
                            ->label('優先度')
                            ->numeric()
                            ->default(0)
                            ->helperText('数値が大きいほど優先的に使用'),
                        
                        Forms\Components\Toggle::make('is_active')
                            ->label('有効')
                            ->default(true),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('利用条件')
                    ->schema([
                        Forms\Components\Toggle::make('allow_new_customers')
                            ->label('新規顧客を許可')
                            ->default(true)
                            ->reactive()
                            ->helperText('新規のお客様がこのラインを利用できるか'),
                        
                        Forms\Components\Toggle::make('allow_existing_customers')
                            ->label('既存顧客を許可')
                            ->default(true)
                            ->helperText('既存のお客様がこのラインを利用できるか'),
                        
                        Forms\Components\Toggle::make('requires_staff')
                            ->label('スタッフ指定必須')
                            ->default(false)
                            ->helperText('小山・新宿店などスタッフ指名制の場合'),
                        
                        Forms\Components\Toggle::make('allows_simultaneous')
                            ->label('同時施術可能')
                            ->default(false)
                            ->helperText('複数の顧客を同時に施術可能か'),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('機材管理')
                    ->schema([
                        Forms\Components\TextInput::make('equipment_id')
                            ->label('機材ID')
                            ->placeholder('例：MACHINE_01'),
                        
                        Forms\Components\TextInput::make('equipment_name')
                            ->label('機材名')
                            ->placeholder('例：アイトレーニング機1号機'),
                    ])
                    ->columns(2)
                    ->collapsed(),
                
                Forms\Components\Section::make('利用可能ルール')
                    ->schema([
                        Forms\Components\KeyValue::make('availability_rules')
                            ->label('利用可能条件')
                            ->helperText('曜日や時間帯などの条件を設定')
                            ->keyLabel('条件タイプ')
                            ->valueLabel('設定値')
                            ->addButtonLabel('条件を追加'),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label('店舗')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('line_name')
                    ->label('ライン名')
                    ->searchable()
                    ->badge()
                    ->color(fn ($state, $record) => $record->line_type === 'main' ? 'primary' : 'warning'),
                
                Tables\Columns\TextColumn::make('line_type')
                    ->label('種別')
                    ->formatStateUsing(fn ($state) => $state === 'main' ? '本ライン' : '予備ライン')
                    ->badge()
                    ->color(fn ($state) => $state === 'main' ? 'success' : 'info'),
                
                Tables\Columns\TextColumn::make('capacity')
                    ->label('同時可能数')
                    ->alignCenter(),
                
                Tables\Columns\IconColumn::make('allow_new_customers')
                    ->label('新規')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->alignCenter(),
                
                Tables\Columns\IconColumn::make('allow_existing_customers')
                    ->label('既存')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->alignCenter(),
                
                Tables\Columns\IconColumn::make('requires_staff')
                    ->label('スタッフ必須')
                    ->boolean()
                    ->alignCenter(),
                
                Tables\Columns\IconColumn::make('allows_simultaneous')
                    ->label('同時施術')
                    ->boolean()
                    ->alignCenter(),
                
                Tables\Columns\TextColumn::make('priority')
                    ->label('優先度')
                    ->sortable()
                    ->alignCenter(),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean()
                    ->alignCenter(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日')
                    ->dateTime('Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('店舗')
                    ->options(Store::pluck('name', 'id')),
                
                Tables\Filters\SelectFilter::make('line_type')
                    ->label('ライン種別')
                    ->options([
                        'main' => '本ライン',
                        'sub' => '予備ライン',
                    ]),
                
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('有効状態'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('manage_schedule')
                    ->label('スケジュール管理')
                    ->icon('heroicon-o-calendar')
                    ->color('info')
                    ->url(fn ($record) => route('filament.admin.resources.reservation-lines.schedule', $record)),
                
                Tables\Actions\Action::make('duplicate')
                    ->label('複製')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function ($record) {
                        $newLine = $record->replicate();
                        $newLine->line_name = $record->line_name . ' (コピー)';
                        $newLine->save();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('activate')
                        ->label('有効化')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->requiresConfirmation(),
                    
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('無効化')
                        ->icon('heroicon-o-x-mark')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('store_id')
            ->defaultSort('line_type')
            ->defaultSort('line_number');
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
            'index' => Pages\ListReservationLines::route('/'),
            'create' => Pages\CreateReservationLine::route('/create'),
            'view' => Pages\ViewReservationLine::route('/{record}'),
            'edit' => Pages\EditReservationLine::route('/{record}/edit'),
            'schedule' => Pages\ManageLineSchedule::route('/{record}/schedule'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            // \App\Filament\Widgets\ReservationTimelineWidget::class, // 後で実装
        ];
    }
}