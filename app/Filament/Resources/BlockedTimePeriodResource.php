<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlockedTimePeriodResource\Pages;
use App\Models\BlockedTimePeriod;
use App\Models\Store;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;

class BlockedTimePeriodResource extends Resource
{
    protected static ?string $model = BlockedTimePeriod::class;
    protected static ?string $navigationIcon = 'heroicon-o-no-symbol';
    protected static ?string $navigationLabel = '予約ブロック設定';
    protected static ?string $modelLabel = '予約ブロック';
    protected static ?string $pluralModelLabel = '予約ブロック';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('store_id')
                    ->label('店舗')
                    ->options(Store::pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                    
                Forms\Components\DatePicker::make('blocked_date')
                    ->label('ブロック日')
                    ->required()
                    ->minDate(today()),
                    
                Forms\Components\Toggle::make('is_all_day')
                    ->label('終日休み')
                    ->helperText('終日休みの場合はONにしてください')
                    ->default(false)
                    ->reactive()
                    ->afterStateUpdated(function ($state, $set) {
                        if ($state) {
                            // 終日の場合、営業時間全体を設定
                            $set('start_time', '00:00:00');
                            $set('end_time', '23:59:59');
                        }
                    }),
                    
                Forms\Components\TimePicker::make('start_time')
                    ->label('開始時間')
                    ->required()
                    ->seconds(false)
                    ->visible(fn($get) => !$get('is_all_day'))
                    ->default('09:00'),
                    
                Forms\Components\TimePicker::make('end_time')
                    ->label('終了時間')
                    ->required()
                    ->seconds(false)
                    ->after('start_time')
                    ->visible(fn($get) => !$get('is_all_day'))
                    ->default('18:00'),
                    
                Forms\Components\TextInput::make('reason')
                    ->label('理由')
                    ->placeholder(fn($get) => $get('is_all_day') 
                        ? '例：臨時休業、年末年始、お盆休み' 
                        : '例：研修、ミーティング、設備メンテナンス')
                    ->maxLength(255),
                    
                Forms\Components\Toggle::make('is_recurring')
                    ->label('繰り返し設定')
                    ->helperText('毎週同じ時間帯をブロックする場合はON')
                    ->default(false)
                    ->reactive(),
                    
                Forms\Components\Select::make('recurrence_pattern')
                    ->label('繰り返しパターン')
                    ->options([
                        'weekly' => '毎週',
                        'monthly' => '毎月',
                    ])
                    ->visible(fn($get) => $get('is_recurring')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label('店舗')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('blocked_date')
                    ->label('日付')
                    ->date('Y/m/d')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('time_range')
                    ->label('時間帯')
                    ->getStateUsing(function ($record) {
                        if ($record->is_all_day) {
                            return '終日';
                        }
                        return substr($record->start_time, 0, 5) . ' - ' . substr($record->end_time, 0, 5);
                    })
                    ->badge()
                    ->color(fn($record) => $record->is_all_day ? 'danger' : 'gray'),
                    
                Tables\Columns\TextColumn::make('reason')
                    ->label('理由')
                    ->limit(30),
                    
                Tables\Columns\IconColumn::make('is_recurring')
                    ->label('繰り返し')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('店舗')
                    ->options(Store::pluck('name', 'id')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('blocked_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlockedTimePeriods::route('/'),
            'create' => Pages\CreateBlockedTimePeriod::route('/create'),
            'edit' => Pages\EditBlockedTimePeriod::route('/{record}/edit'),
        ];
    }
}