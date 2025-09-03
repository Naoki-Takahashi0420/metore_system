<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuCategoryResource\Pages;
use App\Filament\Resources\MenuCategoryResource\RelationManagers;
use App\Models\MenuCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MenuCategoryResource extends Resource
{
    protected static ?string $model = MenuCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';
    protected static ?string $navigationLabel = 'メニューカテゴリー';
    protected static ?string $modelLabel = 'メニューカテゴリー';
    protected static ?string $pluralLabel = 'メニューカテゴリー';
    protected static ?string $navigationGroup = 'メニュー管理';
    protected static ?int $navigationSort = 1;

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
                            ->disabled(!auth()->user()->hasRole('super_admin'))
                            ->default(auth()->user()->store_id)
                            ->visible(auth()->user()->hasRole('super_admin')),

                        Forms\Components\TextInput::make('name')
                            ->label('カテゴリー名')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('例: ケアコース、水素コース'),

                        Forms\Components\TextInput::make('slug')
                            ->label('スラッグ')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('URL用の識別子（自動生成されます）'),

                        Forms\Components\Textarea::make('description')
                            ->label('説明')
                            ->rows(3)
                            ->maxLength(500),

                        Forms\Components\FileUpload::make('image_path')
                            ->label('カテゴリー画像')
                            ->image()
                            ->directory('category-images')
                            ->imageEditor()
                            ->maxSize(5120)
                            ->helperText('推奨サイズ: 1200x600px')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('表示順')
                            ->numeric()
                            ->default(0)
                            ->helperText('小さい数字が先に表示されます'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('有効')
                            ->default(true)
                            ->helperText('無効にするとメニューが非表示になります'),
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
                    ->sortable()
                    ->visible(auth()->user()->hasRole('super_admin')),

                Tables\Columns\ImageColumn::make('image_path')
                    ->label('画像')
                    ->square()
                    ->size(50),

                Tables\Columns\TextColumn::make('name')
                    ->label('カテゴリー名')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('menus_count')
                    ->label('メニュー数')
                    ->counts('menus')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'danger',
                        $state < 3 => 'warning',
                        default => 'success',
                    }),


                Tables\Columns\TextColumn::make('sort_order')
                    ->label('表示順')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日時')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('店舗')
                    ->relationship('store', 'name')
                    ->visible(auth()->user()->hasRole('super_admin')),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('有効状態')
                    ->boolean()
                    ->trueLabel('有効のみ')
                    ->falseLabel('無効のみ')
                    ->placeholder('すべて'),
            ])
            ->actions([
                Tables\Actions\Action::make('manage_menus')
                    ->label('メニュー管理')
                    ->icon('heroicon-o-list-bullet')
                    ->color('success')
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record]))
                    ->tooltip('このカテゴリーのメニューを管理'),
                Tables\Actions\Action::make('duplicate')
                    ->label('他店舗へ複製')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->visible(fn () => auth()->user()->hasRole('super_admin'))
                    ->form([
                        Forms\Components\Select::make('target_store_id')
                            ->label('複製先の店舗')
                            ->options(fn (MenuCategory $record) => \App\Models\Store::where('id', '!=', $record->store_id)
                                ->pluck('name', 'id'))
                            ->required()
                            ->helperText('このカテゴリーと全メニューを選択した店舗へ複製します'),
                    ])
                    ->action(function (MenuCategory $record, array $data) {
                        try {
                            \DB::transaction(function () use ($record, $data) {
                                // カテゴリーを複製（必要なフィールドのみコピー）
                                $newCategory = new \App\Models\MenuCategory();
                                $newCategory->name = $record->name;
                                $newCategory->slug = \Str::slug($record->name . '-' . uniqid());
                                $newCategory->description = $record->description;
                                $newCategory->image_path = $record->image_path;
                                $newCategory->sort_order = $record->sort_order;
                                $newCategory->is_active = $record->is_active;
                                $newCategory->store_id = $data['target_store_id'];
                                $newCategory->save();
                                
                                // メニューも複製
                                foreach ($record->menus as $menu) {
                                    $newMenu = $menu->replicate();
                                    $newMenu->category_id = $newCategory->id;  // menu_category_idではなくcategory_id
                                    $newMenu->store_id = $data['target_store_id'];
                                    $newMenu->save();
                                    
                                    // メニューオプションも複製
                                    if ($menu->options) {
                                        foreach ($menu->options as $option) {
                                            $newOption = $option->replicate();
                                            $newOption->menu_id = $newMenu->id;
                                            $newOption->save();
                                        }
                                    }
                                }
                            });
                            
                            \Filament\Notifications\Notification::make()
                                ->title('複製完了')
                                ->body('カテゴリーとメニューを複製しました')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('複製失敗')
                                ->body('エラーが発生しました: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make()
                    ->label('編集')
                    ->tooltip('カテゴリー設定を編集'),
                Tables\Actions\DeleteAction::make()
                    ->before(function (MenuCategory $record) {
                        // カテゴリーに紐づくメニューがある場合は削除不可
                        if ($record->menus()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title('削除できません')
                                ->body('このカテゴリーにメニューが登録されています。')
                                ->danger()
                                ->send();
                            
                            return false;
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // 店舗管理者は自店舗のみ表示
        if (!auth()->user()->hasRole('super_admin') && auth()->user()->store_id) {
            $query->where('store_id', auth()->user()->store_id);
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MenusRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenuCategories::route('/'),
            'create' => Pages\CreateMenuCategory::route('/create'),
            'edit' => Pages\EditMenuCategory::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'store_manager']);
    }

    public static function canEdit(Model $record): bool
    {
        if (auth()->user()->hasRole('super_admin')) {
            return true;
        }

        return $record->store_id === auth()->user()->store_id;
    }

    public static function canDelete(Model $record): bool
    {
        if (!auth()->user()->hasAnyRole(['super_admin', 'store_manager'])) {
            return false;
        }

        // メニューが紐づいている場合は削除不可
        return !$record->menus()->exists();
    }
}