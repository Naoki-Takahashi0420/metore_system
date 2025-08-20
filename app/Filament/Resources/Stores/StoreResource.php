<?php

namespace App\Filament\Resources\Stores;

use App\Filament\Resources\Stores\Pages\CreateStore;
use App\Filament\Resources\Stores\Pages\EditStore;
use App\Filament\Resources\Stores\Pages\ListStores;
use App\Filament\Resources\Stores\Schemas\StoreForm;
use App\Filament\Resources\Stores\Tables\StoresTable;
use App\Models\Store;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-storefront';
    
    protected static ?string $navigationLabel = '店舗管理';
    
    protected static ?string $modelLabel = '店舗';
    
    protected static ?string $pluralModelLabel = '店舗一覧';
    
    protected static string | UnitEnum | null $navigationGroup = '店舗管理';

    public static function form(Schema $schema): Schema
    {
        return StoreForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoresTable::configure($table);
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
            'index' => ListStores::route('/'),
            'create' => CreateStore::route('/create'),
            'edit' => EditStore::route('/{record}/edit'),
        ];
    }
}
