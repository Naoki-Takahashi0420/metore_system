<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FcInvoiceItemTemplateResource\Pages;
use App\Filament\Resources\FcInvoiceItemTemplateResource\RelationManagers;
use App\Models\FcInvoiceItemTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FcInvoiceItemTemplateResource extends Resource
{
    protected static ?string $model = FcInvoiceItemTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationLabel = '請求テンプレート';

    protected static ?string $modelLabel = '請求テンプレート';

    protected static ?string $navigationGroup = 'FC本部管理';

    protected static ?int $navigationSort = 5;

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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('テンプレート名')
                    ->placeholder('例: 月額ロイヤリティ')
                    ->required(),
                Forms\Components\Select::make('type')
                    ->label('種別')
                    ->options([
                        'royalty' => 'ロイヤリティ',
                        'system_fee' => 'システム使用料',
                        'custom' => 'その他',
                    ])
                    ->default('custom')
                    ->required(),
                Forms\Components\TextInput::make('description')
                    ->label('項目名（請求書に表示）')
                    ->placeholder('例: 月額ロイヤリティ（12月分）')
                    ->required(),
                Forms\Components\TextInput::make('unit_price')
                    ->label('単価')
                    ->numeric()
                    ->prefix('¥')
                    ->required(),
                Forms\Components\TextInput::make('quantity')
                    ->label('数量')
                    ->numeric()
                    ->default(1),
                Forms\Components\TextInput::make('tax_rate')
                    ->label('税率')
                    ->numeric()
                    ->suffix('%')
                    ->default(10),
                Forms\Components\Toggle::make('is_active')
                    ->label('有効')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('テンプレート名')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('項目名')
                    ->limit(30),
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('単価')
                    ->money('jpy'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageFcInvoiceItemTemplates::route('/'),
        ];
    }
}
