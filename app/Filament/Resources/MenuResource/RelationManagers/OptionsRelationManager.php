<?php

namespace App\Filament\Resources\MenuResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';
    protected static ?string $title = 'オプション設定';
    protected static ?string $modelLabel = 'オプション';
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('オプション名')
                    ->required()
                    ->maxLength(255),
                    
                Forms\Components\Textarea::make('description')
                    ->label('説明')
                    ->rows(2)
                    ->columnSpanFull(),
                    
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('追加料金')
                            ->numeric()
                            ->prefix('¥')
                            ->default(0)
                            ->required(),
                            
                        Forms\Components\TextInput::make('duration_minutes')
                            ->label('追加時間（分）')
                            ->numeric()
                            ->suffix('分')
                            ->default(0)
                            ->required(),
                            
                        Forms\Components\TextInput::make('max_quantity')
                            ->label('最大選択数')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(10)
                            ->required(),
                    ]),
                    
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('有効')
                            ->default(true),
                            
                        Forms\Components\Toggle::make('is_required')
                            ->label('必須オプション')
                            ->default(false)
                            ->helperText('チェックすると必ず選択される'),
                            
                        Forms\Components\TextInput::make('sort_order')
                            ->label('表示順')
                            ->numeric()
                            ->default(0),
                    ]),
            ]);
    }
    
    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('順')
                    ->width('50px')
                    ->alignCenter()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('オプション名')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('price')
                    ->label('追加料金')
                    ->money('jpy')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('追加時間')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "+{$state}分" : '-')
                    ->alignCenter(),
                    
                Tables\Columns\TextColumn::make('max_quantity')
                    ->label('最大数')
                    ->alignCenter(),
                    
                Tables\Columns\IconColumn::make('is_required')
                    ->label('必須')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('danger')
                    ->falseColor('gray'),
                    
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('有効'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_required')
                    ->label('種別')
                    ->options([
                        true => '必須オプション',
                        false => '任意オプション',
                    ]),
                    
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('状態'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('オプション追加'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('一括有効化')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('一括無効化')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}