<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FcProductCategoryResource\Pages;
use App\Models\FcProductCategory;
use App\Models\Store;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FcProductCategoryResource extends Resource
{
    protected static ?string $model = FcProductCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'FC商品カテゴリ';

    protected static ?string $modelLabel = 'FC商品カテゴリ';

    protected static ?string $pluralModelLabel = 'FC商品カテゴリ';

    protected static ?string $navigationGroup = 'FC本部管理';

    protected static ?int $navigationSort = 1;

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
                Forms\Components\Section::make('カテゴリ情報')
                    ->schema([
                        Forms\Components\Hidden::make('headquarters_store_id')
                            ->default(fn () => Store::where('fc_type', 'headquarters')->first()?->id ?? 1),
                        Forms\Components\TextInput::make('name')
                            ->label('カテゴリ名')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Textarea::make('description')
                            ->label('説明')
                            ->rows(3)
                            ->maxLength(500),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('表示順')
                            ->numeric()
                            ->default(0),
                        Forms\Components\Toggle::make('is_active')
                            ->label('有効')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('カテゴリ名')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('商品数')
                    ->counts('products')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('表示順')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('有効'),
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
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order');
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
            'index' => Pages\ListFcProductCategories::route('/'),
            'create' => Pages\CreateFcProductCategory::route('/create'),
            'edit' => Pages\EditFcProductCategory::route('/{record}/edit'),
        ];
    }
}
