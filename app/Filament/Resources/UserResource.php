<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'ユーザー管理';

    protected static ?string $modelLabel = 'ユーザー';

    protected static ?string $pluralModelLabel = 'ユーザー';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('名前')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('メールアドレス')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('password')
                            ->label('パスワード')
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->maxLength(255),
                        Forms\Components\Select::make('store_id')
                            ->label('所属店舗')
                            ->relationship('store', 'name')
                            ->searchable()
                            ->nullable(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('権限設定')
                    ->schema([
                        Forms\Components\Select::make('roles')
                            ->label('ロール')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->required(),
                        Forms\Components\DateTimePicker::make('email_verified_at')
                            ->label('メール確認日時')
                            ->nullable(),
                    ]),

                Forms\Components\Section::make('プロフィール')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('電話番号')
                            ->tel()
                            ->maxLength(20),
                        Forms\Components\Select::make('status')
                            ->label('状態')
                            ->options([
                                'active' => '有効',
                                'inactive' => '無効',
                            ])
                            ->default('active')
                            ->required(),
                        Forms\Components\Toggle::make('is_available')
                            ->label('予約受付可能')
                            ->helperText('スタッフとして予約を受け付けるかどうか'),
                        Forms\Components\Textarea::make('bio')
                            ->label('自己紹介')
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('名前')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('メールアドレス')
                    ->searchable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('所属店舗')
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('ロール')
                    ->badge()
                    ->separator(','),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('状態')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => '有効',
                        'inactive' => '無効',
                        default => $state,
                    }),
                Tables\Columns\IconColumn::make('is_available')
                    ->label('予約受付')
                    ->boolean(),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->label('メール確認')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('登録日')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('店舗')
                    ->relationship('store', 'name'),
                Tables\Filters\SelectFilter::make('roles')
                    ->label('ロール')
                    ->relationship('roles', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('状態')
                    ->options([
                        'active' => '有効',
                        'inactive' => '無効',
                    ]),
                Tables\Filters\TernaryFilter::make('is_available')
                    ->label('予約受付可能'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}