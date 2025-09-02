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

                Forms\Components\Section::make('時間・料金設定')
                    ->description('このカテゴリーで提供可能な時間と料金を設定')
                    ->schema([
                        Forms\Components\CheckboxList::make('available_durations')
                            ->label('提供時間')
                            ->options([
                                0 => 'オプション（時間なし）',
                                30 => '30分',
                                50 => '50分',  
                                80 => '80分',
                            ])
                            ->columns(3)
                            ->reactive()
                            ->helperText('チェックした時間のメニューが作成可能になります'),

                        Forms\Components\Grid::make(1)
                            ->schema(fn ($get) => collect($get('available_durations') ?? [])
                                ->map(fn ($duration) => 
                                    Forms\Components\TextInput::make("duration_prices.{$duration}")
                                        ->label("{$duration}分の基本料金")
                                        ->numeric()
                                        ->prefix('¥')
                                        ->required()
                                        ->default(match($duration) {
                                            30 => 3000,
                                            50 => 5000,
                                            80 => 8000,
                                            default => 0
                                        })
                                )
                                ->toArray()
                            ),
                    ]),
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

                Tables\Columns\TextColumn::make('available_durations')
                    ->label('提供時間')
                    ->formatStateUsing(function ($state) {
                        if (!$state || (is_array($state) && empty($state))) {
                            return '未設定';
                        }
                        if (is_string($state)) {
                            $state = json_decode($state, true);
                        }
                        if (!$state || empty($state)) {
                            return '未設定';
                        }
                        return collect($state)->map(fn($d) => "{$d}分")->join(', ');
                    })
                    ->badge()
                    ->color(fn ($state) => (!$state || empty($state)) ? 'gray' : 'info'),

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