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
                    ->minDate(now()),
                    
                Forms\Components\TimePicker::make('start_time')
                    ->label('開始時間')
                    ->required()
                    ->seconds(false),
                    
                Forms\Components\TimePicker::make('end_time')
                    ->label('終了時間')
                    ->required()
                    ->seconds(false)
                    ->after('start_time'),
                    
                Forms\Components\TextInput::make('reason')
                    ->label('理由')
                    ->placeholder('例：研修、ミーティング、設備メンテナンス')
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
                    
                Tables\Columns\TextColumn::make('start_time')
                    ->label('開始時間')
                    ->time('H:i'),
                    
                Tables\Columns\TextColumn::make('end_time')
                    ->label('終了時間')
                    ->time('H:i'),
                    
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