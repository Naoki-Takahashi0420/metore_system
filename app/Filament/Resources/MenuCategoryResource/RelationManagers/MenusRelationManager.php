<?php

namespace App\Filament\Resources\MenuCategoryResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MenusRelationManager extends RelationManager
{
    protected static string $relationship = 'menus';
    protected static ?string $title = 'このカテゴリーのメニュー';
    protected static ?string $modelLabel = 'メニュー';
    protected static ?string $pluralModelLabel = 'メニュー';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('メニュー名')
                            ->required()
                            ->maxLength(100),
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
                        Forms\Components\TextInput::make('default_contract_months')
                            ->label('契約期間')
                            ->numeric()
                            ->suffix('ヶ月')
                            ->default(1)
                            ->required(fn (Forms\Get $get) => $get('is_subscription'))
                            ->helperText('最低契約期間'),
                        Forms\Components\TextInput::make('max_monthly_usage')
                            ->label('月間利用回数上限')
                            ->numeric()
                            ->suffix('回')
                            ->helperText('空欄の場合は無制限'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('通常メニュー料金設定')
                    ->visible(fn (Forms\Get $get) => !$get('is_subscription'))
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('料金')
                            ->numeric()
                            ->required(fn (Forms\Get $get) => !$get('is_subscription'))
                            ->prefix('¥')
                            ->suffixIcon('heroicon-m-currency-yen'),
                        Forms\Components\Select::make('duration_minutes')
                            ->label('所要時間')
                            ->options([
                                0 => 'オプション（時間なし）',
                                15 => '15分',
                                30 => '30分',
                                45 => '45分',
                                60 => '60分（1時間）',
                                75 => '75分（1時間15分）',
                                90 => '90分（1時間30分）',
                                105 => '105分（1時間45分）',
                                120 => '120分（2時間）',
                            ])
                            ->reactive()
                            ->required(fn (Forms\Get $get) => !$get('is_subscription'))
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
                            ->options(function () {
                                $storeId = $this->getOwnerRecord()->store_id;
                                if (!$storeId) {
                                    return [];
                                }

                                return \App\Models\Menu::where('is_subscription', true)
                                    ->where('is_available', true)
                                    ->where('store_id', $storeId)
                                    ->orderBy('name')
                                    ->pluck('name', 'id');
                            })
                            ->helperText('このメニューを利用できるサブスクプラン（この店舗のプランのみ表示）')
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
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('その他')
                    ->schema([
                        Forms\Components\TextInput::make('sort_order')
                            ->label('表示順')
                            ->numeric()
                            ->default(0)
                            ->helperText('小さい数字が先に表示されます'),
                    ])
                    ->columns(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('画像')
                    ->square()
                    ->size(50),
                Tables\Columns\TextColumn::make('name')
                    ->label('メニュー名')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('通常料金')
                    ->money('JPY')
                    ->sortable()
                    ->getStateUsing(fn ($record) => $record->is_subscription ? '-' : $record->price),
                Tables\Columns\TextColumn::make('subscription_monthly_price')
                    ->label('月額料金')
                    ->money('JPY')
                    ->sortable()
                    ->getStateUsing(fn ($record) => $record->is_subscription ? $record->subscription_monthly_price : '-'),
                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('時間')
                    ->formatStateUsing(fn (?int $state): string => $state ? "{$state}分" : '-')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_subscription')
                    ->label('サブスク')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_available')
                    ->label('利用可')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_visible_to_customer')
                    ->label('顧客表示')
                    ->boolean(),
                Tables\Columns\IconColumn::make('show_in_upsell')
                    ->label('オプション')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('表示順')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_subscription')
                    ->label('サブスクメニュー'),
                Tables\Filters\TernaryFilter::make('is_available')
                    ->label('利用可能'),
                Tables\Filters\TernaryFilter::make('is_visible_to_customer')
                    ->label('顧客表示'),
                Tables\Filters\TernaryFilter::make('show_in_upsell')
                    ->label('オプション'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('メニューを追加')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['store_id'] = $this->ownerRecord->store_id;
                        $data['category_id'] = $this->ownerRecord->id;
                        $data['category'] = $this->ownerRecord->name;
                        return $data;
                    }),
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
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }
}