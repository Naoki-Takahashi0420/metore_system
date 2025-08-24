<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShiftResource\Pages;
use App\Filament\Resources\ShiftResource\RelationManagers;
use App\Models\Shift;
use App\Models\User;
use App\Models\Store;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Carbon\Carbon;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    
    protected static ?string $navigationLabel = 'シフト管理';
    
    protected static ?string $modelLabel = 'シフト';
    
    protected static ?string $pluralModelLabel = 'シフト';
    
    protected static ?string $navigationGroup = 'スタッフ管理';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('スタッフ')
                            ->options(User::where('role', 'staff')->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('store_id')
                            ->label('店舗')
                            ->options(Store::pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        Forms\Components\DatePicker::make('shift_date')
                            ->label('シフト日')
                            ->required()
                            ->minDate(now())
                            ->native(false)
                            ->displayFormat('Y年m月d日'),
                    ])->columns(3),
                
                Forms\Components\Section::make('勤務時間')
                    ->schema([
                        Forms\Components\TimePicker::make('start_time')
                            ->label('開始時刻')
                            ->required()
                            ->seconds(false)
                            ->native(false),
                        Forms\Components\TimePicker::make('end_time')
                            ->label('終了時刻')
                            ->required()
                            ->seconds(false)
                            ->native(false)
                            ->after('start_time'),
                        Forms\Components\TimePicker::make('break_start')
                            ->label('休憩開始')
                            ->seconds(false)
                            ->native(false),
                        Forms\Components\TimePicker::make('break_end')
                            ->label('休憩終了')
                            ->seconds(false)
                            ->native(false)
                            ->after('break_start'),
                    ])->columns(4),
                
                Forms\Components\Section::make('その他')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('ステータス')
                            ->options([
                                'scheduled' => '予定',
                                'working' => '勤務中',
                                'completed' => '完了',
                                'cancelled' => 'キャンセル',
                            ])
                            ->default('scheduled')
                            ->required(),
                        Forms\Components\Toggle::make('is_available_for_reservation')
                            ->label('予約受付可能')
                            ->default(true)
                            ->helperText('このシフト中に予約を受け付けるかどうか'),
                        Forms\Components\Textarea::make('notes')
                            ->label('備考')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shift_date')
                    ->label('日付')
                    ->date('Y年m月d日')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('スタッフ')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('店舗')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('開始')
                    ->time('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_time')
                    ->label('終了')
                    ->time('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('working_hours')
                    ->label('実働時間')
                    ->getStateUsing(fn (Shift $record) => $record->working_hours . '時間')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('ステータス')
                    ->colors([
                        'gray' => 'scheduled',
                        'warning' => 'working',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'scheduled' => '予定',
                        'working' => '勤務中',
                        'completed' => '完了',
                        'cancelled' => 'キャンセル',
                        default => $state,
                    }),
                Tables\Columns\IconColumn::make('is_available_for_reservation')
                    ->label('予約受付')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('勤務中')
                    ->getStateUsing(fn (Shift $record) => $record->is_active)
                    ->boolean()
                    ->trueIcon('heroicon-o-signal')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('スタッフ')
                    ->options(User::where('role', 'staff')->pluck('name', 'id'))
                    ->searchable(),
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('店舗')
                    ->options(Store::pluck('name', 'id'))
                    ->searchable(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('ステータス')
                    ->options([
                        'scheduled' => '予定',
                        'working' => '勤務中',
                        'completed' => '完了',
                        'cancelled' => 'キャンセル',
                    ]),
                Tables\Filters\Filter::make('today')
                    ->label('今日のシフト')
                    ->query(fn (Builder $query) => $query->today()),
                Tables\Filters\Filter::make('this_week')
                    ->label('今週のシフト')
                    ->query(fn (Builder $query) => $query->thisWeek()),
                Tables\Filters\Filter::make('available')
                    ->label('予約受付可能')
                    ->query(fn (Builder $query) => $query->available()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('shift_date', 'desc');
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
            'index' => Pages\ListShifts::route('/'),
            'create' => Pages\CreateShift::route('/create'),
            'edit' => Pages\EditShift::route('/{record}/edit'),
            'calendar' => Pages\ShiftCalendar::route('/calendar'),
            'time-tracking' => Pages\TimeTracking::route('/time-tracking'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::today()->count() ?: null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }
}