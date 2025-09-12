<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuResource\Pages;
use App\Models\Menu;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'メニュー管理';

    protected static ?string $modelLabel = 'メニュー';

    protected static ?string $pluralModelLabel = 'メニュー';

    protected static ?string $navigationGroup = 'メニュー管理';
    protected static ?int $navigationSort = 2;

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
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                // 店舗変更時にカテゴリをリセット
                                $set('category_id', null);
                            })
                            ->searchable(),
                        Forms\Components\TextInput::make('name')
                            ->label('メニュー名')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Select::make('category_id')
                            ->label('カテゴリー')
                            ->options(function (Forms\Get $get) {
                                $storeId = $get('store_id');
                                if (!$storeId) {
                                    return ['まず店舗を選択してください'];
                                }
                                
                                // 選択された店舗のカテゴリのみ表示
                                return \App\Models\MenuCategory::where('store_id', $storeId)
                                    ->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->reactive()
                            ->disabled(fn (Forms\Get $get) => !$get('store_id'))
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                // カテゴリー選択時の自動設定は一旦無効化（エラー回避）
                            })
                            ->helperText('選択した店舗のカテゴリーのみ表示されます'),
                        Forms\Components\Textarea::make('description')
                            ->label('説明')
                            ->rows(3)
                            ->maxLength(500),
                        Forms\Components\FileUpload::make('image_path')
                            ->label('メニュー画像')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '16:9',
                            ])
                            ->imageResizeMode('force')
                            ->imageResizeTargetWidth(1920)
                            ->imageResizeTargetHeight(1080)
                            ->directory('menus')
                            ->disk('public')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'])
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('メニュータイプ選択')
                    ->schema([
                        Forms\Components\Toggle::make('is_subscription')
                            ->label('サブスクリプションメニューとして提供')
                            ->default(false)
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    // サブスクメニューの場合、priceを0に設定
                                    $set('price', 0);
                                    // duration_minutesはデフォルト値のまま維持（60分）
                                }
                            })
                            ->helperText('ONにすると月額プランとして、OFFにすると通常メニューとして提供されます'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('サブスクリプション料金設定')
                    ->visible(fn (Forms\Get $get) => $get('is_subscription'))
                    ->schema([
                        Forms\Components\TextInput::make('subscription_monthly_price')
                            ->label('月額料金')
                            ->numeric()
                            ->prefix('¥')
                            ->required(fn (Forms\Get $get) => $get('is_subscription'))
                            ->helperText('毎月のサブスクリプション料金'),
                        Forms\Components\TextInput::make('contract_months')
                            ->label('契約期間')
                            ->numeric()
                            ->suffix('ヶ月')
                            ->default(12)
                            ->required(fn (Forms\Get $get) => $get('is_subscription'))
                            ->helperText('このメニューの契約期間'),
                        Forms\Components\TextInput::make('max_monthly_usage')
                            ->label('月間利用回数上限')
                            ->numeric()
                            ->suffix('回')
                            ->helperText('空欄の場合は無制限'),
                        Forms\Components\Select::make('duration_minutes')
                            ->label('所要時間')
                            ->options(function (Forms\Get $get) {
                                // 15分刻みの選択肢を提供
                                return [
                                    15 => '15分',
                                    30 => '30分',
                                    45 => '45分',
                                    60 => '60分（1時間）',
                                    75 => '75分（1時間15分）',
                                    90 => '90分（1時間30分）',
                                    105 => '105分（1時間45分）',
                                    120 => '120分（2時間）',
                                ];
                            })
                            ->reactive()
                            ->required(fn (Forms\Get $get) => $get('is_subscription'))
                            ->default(60)
                            ->dehydrated()
                            ->helperText('施術にかかる時間'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('通常メニュー料金設定')
                    ->visible(fn (Forms\Get $get) => !$get('is_subscription'))
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('料金')
                            ->numeric()
                            ->required(fn (Forms\Get $get) => !$get('is_subscription'))
                            ->default(0)
                            ->dehydrated()
                            ->prefix('¥')
                            ->suffixIcon('heroicon-m-currency-yen'),
                        Forms\Components\Select::make('duration_minutes')
                            ->label('所要時間')
                            ->options(function (Forms\Get $get) {
                                // 15分刻みの選択肢を提供
                                return [
                                    0 => 'オプション（時間なし）',
                                    15 => '15分',
                                    30 => '30分',
                                    45 => '45分',
                                    60 => '60分（1時間）',
                                    75 => '75分（1時間15分）',
                                    90 => '90分（1時間30分）',
                                    105 => '105分（1時間45分）',
                                    120 => '120分（2時間）',
                                ];
                            })
                            ->reactive()
                            ->required(fn (Forms\Get $get) => !$get('is_subscription'))
                            ->default(60)
                            ->dehydrated()
                            ->helperText('施術にかかる時間'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('表示設定')
                    ->schema([
                        Forms\Components\Toggle::make('is_available')
                            ->label('利用可能')
                            ->default(true)
                            ->helperText('一時的に利用停止する場合はOFF'),
                        Forms\Components\Toggle::make('is_visible_to_customer')
                            ->label('顧客に表示')
                            ->default(true)
                            ->helperText('管理画面のみで使用する場合はOFF'),
                        Forms\Components\Toggle::make('is_subscription_only')
                            ->label('サブスク会員限定')
                            ->default(false)
                            ->visible(fn (Forms\Get $get) => !$get('is_subscription'))
                            ->helperText('サブスク契約者のみ予約可能にする')
                            ->reactive(),
                        Forms\Components\Select::make('subscription_plan_ids')
                            ->label('対象サブスクプラン')
                            ->multiple()
                            ->options(\App\Models\SubscriptionPlan::where('is_active', true)->pluck('name', 'id'))
                            ->helperText('このメニューを利用できるサブスクプラン')
                            ->visible(fn (Forms\Get $get) => $get('is_subscription_only') && !$get('is_subscription')),
                        Forms\Components\Toggle::make('requires_staff')
                            ->label('スタッフ指定必須')
                            ->default(false)
                            ->helperText('予約時にスタッフ選択を必須にする'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('オプションメニュー設定')
                    ->visible(fn (Forms\Get $get) => !$get('is_subscription'))
                    ->schema([
                        Forms\Components\Toggle::make('show_in_upsell')
                            ->label('追加オプションとして提案')
                            ->helperText('ONにすると「ご一緒にいかがですか？」で追加提案されます')
                            ->reactive()
                            ->default(false),
                        Forms\Components\Textarea::make('upsell_description')
                            ->label('追加提案メッセージ')
                            ->placeholder('例：お疲れの目をさらにケアしませんか？')
                            ->rows(2)
                            ->maxLength(200)
                            ->visible(fn($get) => $get('show_in_upsell'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('予約窓口制限')
                    ->schema([
                        Forms\Components\Select::make('customer_type_restriction')
                            ->label('表示する予約窓口')
                            ->options([
                                'all' => '全ての窓口（新規予約・カルテ両方）',
                                'new' => '新規予約窓口のみ',
                                'existing' => 'カルテからの予約のみ',
                            ])
                            ->default('all')
                            ->helperText('どの予約窓口でこのメニューを表示するか選択')
                            ->reactive()
                            ->columnSpanFull()
                            ->dehydrated(), // 値が確実に保存されるように
                    ])
                    ->columns(1),
                    
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label('店舗')
                    ->sortable(),
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('画像')
                    ->square()
                    ->size(50),
                Tables\Columns\TextColumn::make('name')
                    ->label('メニュー名')
                    ->searchable(),
                Tables\Columns\TextColumn::make('menuCategory.name')
                    ->label('カテゴリー')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('料金')
                    ->money('JPY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('所要時間')
                    ->formatStateUsing(fn (?int $state): string => $state ? "{$state}分" : '-')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_available')
                    ->label('利用可')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_visible_to_customer')
                    ->label('顧客表示')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_subscription_only')
                    ->label('サブスク')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('登録日')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('店舗')
                    ->relationship('store', 'name'),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('カテゴリー')
                    ->relationship('menuCategory', 'name'),
                Tables\Filters\SelectFilter::make('duration_minutes')
                    ->label('時間')
                    ->options([
                        'vision_training' => '視力トレーニング',
                        'vr_training' => 'VRトレーニング',
                        'eye_care' => 'アイケア',
                        'consultation' => 'カウンセリング',
                        'other' => 'その他',
                    ]),
                Tables\Filters\TernaryFilter::make('is_available')
                    ->label('利用可能'),
                Tables\Filters\TernaryFilter::make('is_visible_to_customer')
                    ->label('顧客表示'),
                Tables\Filters\TernaryFilter::make('is_subscription_only')
                    ->label('サブスク限定'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function getRelations(): array
    {
        return [
            // RelationManagers\OptionsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        // スーパーアドミンは全店舗のメニューにアクセス可能
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // オーナーは紐づいた店舗のメニューのみ表示
        if ($user->hasRole('owner')) {
            $storeIds = $user->ownedStores()->pluck('stores.id')->toArray();
            return $query->whereIn('store_id', $storeIds);
        }

        // 店長・スタッフは自店舗のメニューのみ表示
        if ($user->hasRole(['manager', 'staff'])) {
            return $query->where('store_id', $user->store_id);
        }

        // 該当ロールがない場合は空の結果
        return $query->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenus::route('/'),
            'create' => Pages\CreateMenu::route('/create'),
            'view' => Pages\ViewMenu::route('/{record}'),
            'edit' => Pages\EditMenu::route('/{record}/edit'),
        ];
    }
}