<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = '顧客管理';

    protected static ?string $modelLabel = '顧客';

    protected static ?string $pluralModelLabel = '顧客';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\TextInput::make('last_name')
                            ->label('姓')
                            ->required()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('first_name')
                            ->label('名')
                            ->required()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('last_name_kana')
                            ->label('姓（カナ）')
                            ->required()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('first_name_kana')
                            ->label('名（カナ）')
                            ->required()
                            ->maxLength(50),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('連絡先情報')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('電話番号')
                            ->tel()
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('email')
                            ->label('メールアドレス')
                            ->email()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('address')
                            ->label('住所')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('postal_code')
                            ->label('郵便番号')
                            ->maxLength(10),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('個人情報')
                    ->schema([
                        Forms\Components\DatePicker::make('birth_date')
                            ->label('生年月日'),
                        Forms\Components\Select::make('gender')
                            ->label('性別')
                            ->options([
                                'male' => '男性',
                                'female' => '女性',
                                'other' => 'その他',
                                'prefer_not_to_say' => '回答しない',
                            ]),
                        Forms\Components\TextInput::make('occupation')
                            ->label('職業')
                            ->maxLength(100),
                        Forms\Components\Select::make('referral_source')
                            ->label('紹介経路')
                            ->options([
                                'website' => 'ウェブサイト',
                                'social_media' => 'SNS',
                                'friend' => '友人・知人',
                                'advertisement' => '広告',
                                'walk_in' => '通りすがり',
                                'other' => 'その他',
                            ]),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('通知設定')
                    ->schema([
                        Forms\Components\Toggle::make('sms_notifications_enabled')
                            ->label('SMS通知を受け取る')
                            ->default(true)
                            ->helperText('予約リマインダーなどのSMS通知を受信します'),
                        Forms\Components\Toggle::make('is_blocked')
                            ->label('要注意顧客')
                            ->default(false)
                            ->helperText('問題のある顧客としてマークします')
                            ->columnSpan(1),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('その他')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('有効')
                            ->default(true),
                        Forms\Components\KeyValue::make('preferences')
                            ->label('設定')
                            ->keyLabel('項目')
                            ->valueLabel('値'),
                        Forms\Components\Textarea::make('notes')
                            ->label('備考')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('顧客名')
                    ->formatStateUsing(fn ($record) => $record->last_name . ' ' . $record->first_name)
                    ->searchable(['last_name', 'first_name']),
                Tables\Columns\TextColumn::make('last_name_kana')
                    ->label('顧客名（カナ）')
                    ->formatStateUsing(fn ($record) => $record->last_name_kana . ' ' . $record->first_name_kana)
                    ->searchable(['last_name_kana', 'first_name_kana'])
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('phone')
                    ->label('電話番号')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('メールアドレス')
                    ->searchable(),
                Tables\Columns\TextColumn::make('birth_date')
                    ->label('生年月日')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('gender')
                    ->label('性別')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'male' => '男性',
                        'female' => '女性',
                        'other' => 'その他',
                        'prefer_not_to_say' => '回答しない',
                        default => '',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reservations_count')
                    ->label('予約数')
                    ->counts('reservations')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('登録日')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('有効状態'),
                Tables\Filters\SelectFilter::make('gender')
                    ->label('性別')
                    ->options([
                        'male' => '男性',
                        'female' => '女性',
                        'other' => 'その他',
                        'prefer_not_to_say' => '回答しない',
                    ]),
                Tables\Filters\SelectFilter::make('referral_source')
                    ->label('紹介経路')
                    ->options([
                        'website' => 'ウェブサイト',
                        'social_media' => 'SNS',
                        'friend' => '友人・知人',
                        'advertisement' => '広告',
                        'walk_in' => '通りすがり',
                        'other' => 'その他',
                    ]),
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
            RelationManagers\MedicalRecordsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}