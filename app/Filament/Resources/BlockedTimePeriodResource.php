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
                    ->options(function () {
                        $user = auth()->user();

                        if ($user->hasRole('super_admin')) {
                            return Store::where('is_active', true)->pluck('name', 'id');
                        } elseif ($user->hasRole('owner')) {
                            return $user->manageableStores()
                                ->select('stores.id', 'stores.name', 'stores.is_active')
                                ->where('stores.is_active', true)
                                ->pluck('name', 'stores.id');
                        } else {
                            // 店長・スタッフは自店舗のみ
                            return $user->store ? collect([$user->store->id => $user->store->name]) : collect();
                        }
                    })
                    ->required()
                    ->searchable()
                    ->default(function () {
                        $user = auth()->user();
                        // スタッフ・店長は自店舗をデフォルト選択
                        if ($user->hasRole(['staff', 'manager']) && $user->store_id) {
                            return $user->store_id;
                        }
                        return null;
                    })
                    ->disabled(function () {
                        $user = auth()->user();
                        // スタッフ・店長は店舗選択を変更不可
                        return $user->hasRole(['staff', 'manager']);
                    }),
                    
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

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('作成者')
                    ->sortable()
                    ->searchable()
                    ->default('不明'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日時')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_recurring')
                    ->label('繰り返し')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('店舗')
                    ->relationship('store', 'name')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(function ($record) {
                        $user = auth()->user();
                        // スーパーアドミンとオーナーは常に編集可能
                        if ($user->hasRole(['super_admin', 'owner'])) {
                            return true;
                        }
                        // スタッフと店長は未来の予約ブロックのみ編集可能
                        if ($user->hasRole(['staff', 'manager'])) {
                            return $record->blocked_date->isFuture() || $record->blocked_date->isToday();
                        }
                        return false;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(function ($record) {
                        $user = auth()->user();
                        // スーパーアドミンとオーナーは常に削除可能
                        if ($user->hasRole(['super_admin', 'owner'])) {
                            return true;
                        }
                        // スタッフと店長は未来の予約ブロックのみ削除可能
                        if ($user->hasRole(['staff', 'manager'])) {
                            return $record->blocked_date->isFuture() || $record->blocked_date->isToday();
                        }
                        return false;
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(function () {
                            $user = auth()->user();
                            // スーパーアドミンとオーナーのみ一括削除可能
                            return $user->hasRole(['super_admin', 'owner']);
                        }),
                ]),
            ])
            ->defaultSort('blocked_date', 'desc');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        // スーパーアドミンは全店舗のデータにアクセス可能
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // オーナーは紐づいた店舗のデータのみ表示
        if ($user->hasRole('owner')) {
            $storeIds = $user->manageableStores()
                ->select('stores.id')
                ->pluck('stores.id')
                ->toArray();
            return $query->whereIn('store_id', $storeIds);
        }

        // 店長・スタッフは自店舗のデータのみ表示
        if ($user->hasRole(['manager', 'staff']) && $user->store_id) {
            return $query->where('store_id', $user->store_id);
        }

        // 該当ロールがない場合は空の結果
        return $query->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlockedTimePeriods::route('/'),
            'create' => Pages\CreateBlockedTimePeriod::route('/create'),
            'edit' => Pages\EditBlockedTimePeriod::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        // スタッフは表示不可
        if ($user->hasRole('staff')) {
            return false;
        }

        // super_admin, owner, manager は表示可能
        return $user->hasRole(['super_admin', 'owner', 'manager']);
    }

    public static function canCreate(): bool
    {
        // canViewAnyと同じ条件
        return static::canViewAny();
    }
}