<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerAccessTokenResource\Pages;
use App\Filament\Resources\CustomerAccessTokenResource\RelationManagers;
use App\Models\CustomerAccessToken;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CustomerAccessTokenResource extends Resource
{
    protected static ?string $model = CustomerAccessToken::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('store_id')
                    ->relationship('store', 'name')
                    ->placeholder('全店舗で使用可能（店舗未指定）'),
                Forms\Components\TextInput::make('token')
                    ->placeholder('空欄の場合は自動生成されます')
                    ->helperText('32文字のランダム文字列が自動生成されます'),
                Forms\Components\Select::make('purpose')
                    ->options([
                        'existing_customer' => '既存顧客',
                        'vip' => 'VIP顧客',
                        'campaign' => 'キャンペーン',
                        'promotion' => 'プロモーション',
                        'trial' => 'お試し',
                    ])
                    ->default('existing_customer')
                    ->required(),
                Forms\Components\DateTimePicker::make('expires_at')
                    ->label('有効期限')
                    ->helperText('未設定の場合は6ヶ月後に設定されます'),
                Forms\Components\TextInput::make('usage_count')
                    ->label('使用回数')
                    ->numeric()
                    ->default(0)
                    ->disabled(),
                Forms\Components\TextInput::make('max_usage')
                    ->label('最大使用回数')
                    ->numeric()
                    ->placeholder('未設定の場合は無制限'),
                Forms\Components\Textarea::make('metadata')
                    ->label('メタデータ（JSON）')
                    ->placeholder('{"discount_rate": 10, "notes": "特別割引"}')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')
                    ->label('有効')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('顧客名')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('店舗')
                    ->placeholder('全店舗')
                    ->sortable(),
                Tables\Columns\TextColumn::make('token')
                    ->label('トークン')
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->token)
                    ->searchable(),
                Tables\Columns\TextColumn::make('purpose')
                    ->label('目的')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'existing_customer' => 'success',
                        'vip' => 'warning',
                        'campaign' => 'info',
                        'promotion' => 'primary',
                        'trial' => 'gray',
                        default => 'gray',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('有効期限')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('usage_count')
                    ->label('使用回数')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_usage')
                    ->label('最大使用回数')
                    ->placeholder('無制限')
                    ->numeric()
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
                //
            ])
            ->actions([
                Tables\Actions\Action::make('qr_code')
                    ->label('QRコード')
                    ->icon('heroicon-o-qr-code')
                    ->modalHeading('QRコード表示')
                    ->modalContent(function ($record) {
                        $qrCode = QrCode::size(300)->generate($record->getReservationUrl());
                        return view('filament.modals.qr-code', [
                            'qrCode' => $qrCode,
                            'url' => $record->getReservationUrl(),
                            'customer' => $record->customer,
                            'token' => $record
                        ]);
                    })
                    ->modalWidth('lg'),
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
            'index' => Pages\ListCustomerAccessTokens::route('/'),
            'create' => Pages\CreateCustomerAccessToken::route('/create'),
            'edit' => Pages\EditCustomerAccessToken::route('/{record}/edit'),
        ];
    }
}
