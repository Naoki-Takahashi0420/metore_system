<?php

namespace App\Filament\Resources\MenuCategoryResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
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
                Forms\Components\TextInput::make('name')
                    ->label('メニュー名')
                    ->required()
                    ->maxLength(100),
                    
                Forms\Components\Textarea::make('description')
                    ->label('説明')
                    ->rows(2)
                    ->maxLength(500),
                    
                Forms\Components\Select::make('duration_minutes')
                    ->label('所要時間')
                    ->options(function () {
                        $category = $this->ownerRecord;
                        if (!$category || empty($category->available_durations)) {
                            return [
                                30 => '30分',
                                50 => '50分',
                                80 => '80分',
                            ];
                        }
                        
                        $options = [];
                        foreach ($category->available_durations as $duration) {
                            $options[$duration] = "{$duration}分";
                        }
                        return $options;
                    })
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        $category = $this->ownerRecord;
                        if ($category && isset($category->duration_prices[$state])) {
                            $set('price', $category->duration_prices[$state]);
                        }
                    })
                    ->required(),
                    
                Forms\Components\TextInput::make('price')
                    ->label('料金')
                    ->numeric()
                    ->required()
                    ->prefix('¥'),
                    
                Forms\Components\Toggle::make('is_available')
                    ->label('利用可能')
                    ->default(true),
                    
                Forms\Components\Toggle::make('show_in_upsell')
                    ->label('オプションメニュー')
                    ->helperText('ONにすると追加オプションとして表示')
                    ->default(false),
                    
                Forms\Components\TextInput::make('sort_order')
                    ->label('表示順')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('メニュー名')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('時間')
                    ->formatStateUsing(fn (?int $state): string => $state ? "{$state}分" : '-')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('price')
                    ->label('料金')
                    ->money('JPY')
                    ->sortable(),
                    
                Tables\Columns\IconColumn::make('is_available')
                    ->label('利用可')
                    ->boolean(),
                    
                Tables\Columns\IconColumn::make('show_in_upsell')
                    ->label('オプション')
                    ->boolean(),
                    
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('表示順')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_available')
                    ->label('利用可能'),
                    
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