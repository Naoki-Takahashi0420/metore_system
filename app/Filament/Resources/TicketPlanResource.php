<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketPlanResource\Pages;
use App\Models\TicketPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TicketPlanResource extends Resource
{
    protected static ?string $model = TicketPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = '回数券設定';

    protected static ?string $modelLabel = '回数券プラン';

    protected static ?string $pluralModelLabel = '回数券プラン';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationGroup = 'メニュー管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\Select::make('store_id')
                            ->label('店舗')
                            ->relationship('store', 'name')
                            ->required()
                            ->disabled(fn ($operation) => $operation === 'edit')
                            ->helperText(fn ($operation) => $operation === 'edit' ? '店舗の変更はできません' : '回数券を販売する店舗を選択'),

                        Forms\Components\Select::make('menu_id')
                            ->label('対象メニュー')
                            ->relationship(
                                name: 'menu',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query, callable $get) =>
                                    $query->when($get('store_id'), fn ($q, $storeId) =>
                                        $q->where('store_id', $storeId))
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('この回数券で予約できるメニューを選択'),

                        Forms\Components\TextInput::make('name')
                            ->label('回数券名')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('例: 10回券、5回券'),

                        Forms\Components\Textarea::make('description')
                            ->label('説明')
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder('回数券の特徴や利用条件など'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('有効')
                            ->default(true)
                            ->helperText('無効にすると新規販売できなくなります'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('回数・料金設定')
                    ->schema([
                        Forms\Components\TextInput::make('ticket_count')
                            ->label('利用可能回数')
                            ->numeric()
                            ->suffix('回')
                            ->required()
                            ->minValue(1)
                            ->placeholder('例: 10'),

                        Forms\Components\TextInput::make('price')
                            ->label('販売価格')
                            ->numeric()
                            ->prefix('¥')
                            ->required()
                            ->minValue(0)
                            ->placeholder('例: 50000'),

                        Forms\Components\Placeholder::make('price_per_ticket')
                            ->label('1回あたりの単価')
                            ->content(function (Forms\Get $get): string {
                                $count = $get('ticket_count');
                                $price = $get('price');

                                if ($count && $price && $count > 0) {
                                    $perTicket = round($price / $count);
                                    return '¥' . number_format($perTicket);
                                }

                                return '回数と価格を入力すると表示されます';
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('有効期限設定')
                    ->schema([
                        Forms\Components\TextInput::make('validity_months')
                            ->label('有効期限（月）')
                            ->numeric()
                            ->suffix('ヶ月')
                            ->minValue(0)
                            ->placeholder('例: 3')
                            ->helperText('購入日から何ヶ月間有効か'),

                        Forms\Components\TextInput::make('validity_days')
                            ->label('有効期限（日）')
                            ->numeric()
                            ->suffix('日')
                            ->minValue(0)
                            ->placeholder('例: 15')
                            ->helperText('月数に加算する日数（例: 3ヶ月15日）'),

                        Forms\Components\Placeholder::make('validity_info')
                            ->label('有効期限について')
                            ->content('月数・日数の両方が空の場合は無期限回数券になります')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('メモ・備考')
                            ->rows(3)
                            ->placeholder('内部用のメモや注意事項など')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label('店舗')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('menu.name')
                    ->label('対象メニュー')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('回数券名')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ticket_count')
                    ->label('利用回数')
                    ->formatStateUsing(fn ($state) => "{$state}回")
                    ->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('販売価格')
                    ->money('JPY')
                    ->sortable(),

                Tables\Columns\TextColumn::make('price_per_ticket')
                    ->label('1回単価')
                    ->formatStateUsing(fn ($record) =>
                        '¥' . number_format(round($record->price / $record->ticket_count))
                    )
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderByRaw('price / ticket_count ' . $direction);
                    }),

                Tables\Columns\TextColumn::make('validity_description')
                    ->label('有効期限')
                    ->getStateUsing(fn ($record) => $record->validity_description),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean(),

                Tables\Columns\TextColumn::make('customer_tickets_count')
                    ->label('販売数')
                    ->counts('customerTickets')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日')
                    ->dateTime('Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('店舗')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('すべての店舗'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('有効状態')
                    ->placeholder('すべて')
                    ->trueLabel('有効のみ')
                    ->falseLabel('無効のみ'),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('duplicate')
                    ->label('複製')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->action(function ($record) {
                        $newPlan = $record->replicate();
                        $newPlan->name = $record->name . ' (コピー)';
                        $newPlan->is_active = false;
                        $newPlan->save();

                        \Filament\Notifications\Notification::make()
                            ->title('回数券プランを複製しました')
                            ->body('複製したプランは無効状態です。内容を確認して有効化してください。')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('toggle_active')
                    ->label(fn ($record) => $record->is_active ? '無効化' : '有効化')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['is_active' => !$record->is_active]);

                        \Filament\Notifications\Notification::make()
                            ->title($record->is_active ? '有効化しました' : '無効化しました')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListTicketPlans::route('/'),
            'create' => Pages\CreateTicketPlan::route('/create'),
            'view' => Pages\ViewTicketPlan::route('/{record}'),
            'edit' => Pages\EditTicketPlan::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        // スーパーアドミンは全データにアクセス可能
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // オーナーは紐づいた店舗の回数券プランのみ表示
        if ($user->hasRole('owner')) {
            $storeIds = $user->manageableStores()->pluck('stores.id')->toArray();
            return $query->whereIn('store_id', $storeIds);
        }

        // 店長・スタッフは自店舗の回数券プランのみ表示
        if ($user->hasRole(['manager', 'staff'])) {
            return $query->where('store_id', $user->store_id);
        }

        // 該当ロールがない場合は空の結果
        return $query->whereRaw('1 = 0');
    }
}
