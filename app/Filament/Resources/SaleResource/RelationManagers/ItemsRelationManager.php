<?php

namespace App\Filament\Resources\SaleResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = '売上明細';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('menu_id')
                    ->label('メニュー')
                    ->relationship('menu', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('item_name')
                    ->label('商品名')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('unit_price')
                    ->label('単価')
                    ->numeric()
                    ->prefix('¥')
                    ->required(),
                Forms\Components\TextInput::make('quantity')
                    ->label('数量')
                    ->numeric()
                    ->default(1)
                    ->required(),
                Forms\Components\TextInput::make('discount_amount')
                    ->label('割引額')
                    ->numeric()
                    ->prefix('¥')
                    ->default(0),
                Forms\Components\TextInput::make('tax_rate')
                    ->label('税率(%)')
                    ->numeric()
                    ->suffix('%')
                    ->default(10),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('item_name')
            ->columns([
                Tables\Columns\TextColumn::make('item_name')
                    ->label('商品名'),
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('単価')
                    ->money('JPY'),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('数量'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('金額')
                    ->money('JPY'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}