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
                            ->searchable(),
                        Forms\Components\TextInput::make('name')
                            ->label('メニュー名')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Select::make('category')
                            ->label('カテゴリー')
                            ->options([
                                'vision_training' => '視力トレーニング',
                                'vr_training' => 'VRトレーニング',
                                'eye_care' => 'アイケア',
                                'consultation' => 'カウンセリング',
                                'other' => 'その他',
                            ])
                            ->required(),
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

                Forms\Components\Section::make('料金・時間設定')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('料金')
                            ->numeric()
                            ->required()
                            ->prefix('¥')
                            ->suffixIcon('heroicon-m-currency-yen'),
                        Forms\Components\TextInput::make('duration')
                            ->label('所要時間（分）')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(480)
                            ->suffix('分')
                            ->helperText('オプションメニューの場合は0分も可能'),
                        Forms\Components\Toggle::make('is_available')
                            ->label('利用可能')
                            ->default(true),
                        Forms\Components\Toggle::make('show_in_upsell')
                            ->label('オプションメニューとして表示')
                            ->helperText('ONにすると「ご一緒にいかがですか？」で追加提案されます。OFFの場合は通常のメインメニューとして表示されます。')
                            ->default(false),
                        Forms\Components\Textarea::make('upsell_description')
                            ->label('追加提案メッセージ')
                            ->placeholder('例：お疲れの目をさらにケアしませんか？')
                            ->rows(2)
                            ->maxLength(200)
                            ->visible(fn($get) => $get('show_in_upsell')),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('顧客タイプ制限')
                    ->schema([
                        Forms\Components\Select::make('customer_type_restriction')
                            ->label('表示対象の顧客タイプ')
                            ->options([
                                'all' => '全ての顧客',
                                'new' => '新規顧客のみ',
                                'existing' => '既存顧客のみ',
                            ])
                            ->default('all')
                            ->helperText('このメニューを表示する顧客タイプを選択してください')
                            ->reactive(),
                        Forms\Components\Toggle::make('medical_record_only')
                            ->label('カルテからのみ予約可能')
                            ->helperText('ONにすると、このメニューはカルテからの予約でのみ表示されます（一般予約画面では非表示）')
                            ->default(false)
                            ->visible(fn($get) => $get('customer_type_restriction') === 'existing'),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('詳細設定')
                    ->schema([
                        Forms\Components\TextInput::make('max_capacity')
                            ->label('最大予約人数')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(10),
                        Forms\Components\TextInput::make('display_order')
                            ->label('表示順序')
                            ->numeric()
                            ->default(0)
                            ->helperText('数字が小さいほど上に表示されます（店舗目線で重要なメニューは小さい番号を）'),
                        Forms\Components\TagsInput::make('tags')
                            ->label('タグ')
                            ->placeholder('タグを追加')
                            ->separator(','),
                        Forms\Components\KeyValue::make('requirements')
                            ->label('必要な準備・持ち物')
                            ->keyLabel('項目')
                            ->valueLabel('詳細')
                            ->addButtonLabel('項目を追加'),
                        Forms\Components\Repeater::make('benefits')
                            ->label('効果・メリット')
                            ->schema([
                                Forms\Components\TextInput::make('benefit')
                                    ->label('効果')
                                    ->required(),
                            ])
                            ->columns(1)
                            ->addActionLabel('効果を追加'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label('店舗')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('メニュー名')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('カテゴリー')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'vision_training' => '視力トレーニング',
                        'vr_training' => 'VRトレーニング',
                        'eye_care' => 'アイケア',
                        'consultation' => 'カウンセリング',
                        'other' => 'その他',
                        default => $state,
                    })
                    ->badge(),
                Tables\Columns\TextColumn::make('price')
                    ->label('料金')
                    ->money('JPY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration')
                    ->label('所要時間')
                    ->formatStateUsing(fn (int $state): string => "{$state}分")
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('おすすめ')
                    ->boolean(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('状態')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => '有効',
                        'inactive' => '無効',
                        default => $state,
                    }),
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
                Tables\Filters\SelectFilter::make('category')
                    ->label('カテゴリー')
                    ->options([
                        'vision_training' => '視力トレーニング',
                        'vr_training' => 'VRトレーニング',
                        'eye_care' => 'アイケア',
                        'consultation' => 'カウンセリング',
                        'other' => 'その他',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('状態')
                    ->options([
                        'active' => '有効',
                        'inactive' => '無効',
                    ]),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('おすすめメニュー'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListMenus::route('/'),
            'create' => Pages\CreateMenu::route('/create'),
            'view' => Pages\ViewMenu::route('/{record}'),
            'edit' => Pages\EditMenu::route('/{record}/edit'),
        ];
    }
}