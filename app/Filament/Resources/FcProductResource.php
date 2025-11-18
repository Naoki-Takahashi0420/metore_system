<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FcProductResource\Pages;
use App\Models\FcProduct;
use App\Models\FcProductCategory;
use App\Models\Store;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FcProductResource extends Resource
{
    protected static ?string $model = FcProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'FC商品';

    protected static ?string $modelLabel = 'FC商品';

    protected static ?string $pluralModelLabel = 'FC商品';

    protected static ?string $navigationGroup = 'FC本部管理';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        // super_adminは常に表示
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // 本部店舗のユーザーのみ表示
        return $user->store?->isHeadquarters() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // super_adminと本部店舗のユーザーのみアクセス可能
        if ($user->hasRole('super_admin') || ($user->store && $user->store->isHeadquarters())) {
            return $query;
        }

        // その他のユーザーは何も表示しない
        return $query->whereRaw('1 = 0');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\Hidden::make('headquarters_store_id')
                            ->default(fn () => Store::where('fc_type', 'headquarters')->first()?->id ?? 1),
                        Forms\Components\Select::make('category_id')
                            ->label('カテゴリ')
                            ->options(function () {
                                $headquartersId = Store::where('fc_type', 'headquarters')->first()?->id ?? 1;
                                return FcProductCategory::where('headquarters_store_id', $headquartersId)
                                    ->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->pluck('name', 'id');
                            })
                            ->searchable(),
                        Forms\Components\TextInput::make('sku')
                            ->label('商品コード')
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->default(fn () => \App\Models\FcProduct::generateSku())
                            ->disabled()
                            ->dehydrated()
                            ->helperText('自動で採番されます（例: FC-PRD-0001）'),
                        Forms\Components\TextInput::make('name')
                            ->label('商品名')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Textarea::make('description')
                            ->label('商品説明')
                            ->rows(3)
                            ->maxLength(1000),
                        Forms\Components\FileUpload::make('image_path')
                            ->label('商品画像')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '1:1',
                                '4:3',
                            ])
                            ->directory('fc-products')
                            ->disk('public')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/jpeg', 'image/jpg', 'image/png', 'image/webp'])
                            ->maxSize(5120) // 5MB
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('価格・税率')
                    ->description('FC加盟店への卸売価格を設定します。消費税は自動計算されます。')
                    ->schema([
                        Forms\Components\TextInput::make('unit_price')
                            ->label('卸価格（税抜）')
                            ->numeric()
                            ->required()
                            ->prefix('¥')
                            ->step(1)
                            ->helperText('FC加盟店に販売する1個あたりの価格（税抜き）。例: 10000 → ¥10,000'),
                        Forms\Components\TextInput::make('tax_rate')
                            ->label('税率')
                            ->numeric()
                            ->required()
                            ->default(10.00)
                            ->suffix('%')
                            ->step(0.01)
                            ->helperText('通常は10%。食品・サプリは8%（軽減税率）'),
                        Forms\Components\TextInput::make('unit')
                            ->label('単位')
                            ->default('個')
                            ->maxLength(20)
                            ->helperText('数え方。例: 個、箱、本、セット、パック'),
                        Forms\Components\Placeholder::make('price_example')
                            ->label('価格計算例')
                            ->content(function (Forms\Get $get) {
                                $unitPrice = floatval($get('unit_price') ?? 0);
                                $taxRate = floatval($get('tax_rate') ?? 10);
                                if ($unitPrice <= 0) {
                                    return '卸価格を入力すると自動計算されます';
                                }
                                $taxAmount = $unitPrice * ($taxRate / 100);
                                $totalPrice = $unitPrice + $taxAmount;
                                return sprintf(
                                    '卸価格 ¥%s + 消費税 ¥%s = 税込 ¥%s',
                                    number_format($unitPrice),
                                    number_format($taxAmount),
                                    number_format($totalPrice)
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('在庫・発注設定')
                    ->description('本部の在庫数とFC加盟店からの最小発注数を設定します。')
                    ->schema([
                        Forms\Components\TextInput::make('stock_quantity')
                            ->label('本部在庫数')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('現在、本部に何個在庫があるか'),
                        Forms\Components\TextInput::make('min_order_quantity')
                            ->label('最小発注数')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->helperText('FC加盟店が1回に発注できる最低数量'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('販売中')
                            ->default(true)
                            ->helperText('OFFにするとFC加盟店から発注できなくなります'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('画像')
                    ->disk('public')
                    ->square()
                    ->size(50),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('商品名')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('カテゴリ')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('卸価格')
                    ->money('jpy')
                    ->sortable(),
                Tables\Columns\TextColumn::make('tax_rate')
                    ->label('税率')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('在庫')
                    ->sortable()
                    ->color(fn (FcProduct $record): string =>
                        $record->stock_quantity <= 0 ? 'danger' :
                        ($record->stock_quantity < 10 ? 'warning' : 'success')
                    ),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('販売中')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新日')
                    ->dateTime('Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('カテゴリ')
                    ->relationship('category', 'name'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('販売中'),
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
            ->defaultSort('name', 'asc');
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
            'index' => Pages\ListFcProducts::route('/'),
            'create' => Pages\CreateFcProduct::route('/create'),
            'edit' => Pages\EditFcProduct::route('/{record}/edit'),
        ];
    }
}
