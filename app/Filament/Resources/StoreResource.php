<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreResource\Pages;
use App\Models\Store;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = '店舗管理';

    protected static ?string $modelLabel = '店舗';

    protected static ?string $pluralModelLabel = '店舗';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('店舗名')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('code')
                            ->label('店舗コード')
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->default(fn() => \App\Models\Store::generateStoreCode())
                            ->disabled(fn($record) => $record !== null)
                            ->dehydrated(),
                        Forms\Components\Select::make('status')
                            ->label('状態')
                            ->options([
                                'active' => '営業中',
                                'inactive' => '休業中',
                                'closed' => '閉店',
                            ])
                            ->default('active')
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->label('説明')
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('連絡先情報')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('電話番号')
                            ->tel()
                            ->required(),
                        Forms\Components\TextInput::make('email')
                            ->label('メールアドレス')
                            ->email()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('postal_code')
                            ->label('郵便番号')
                            ->maxLength(10),
                        Forms\Components\TextInput::make('address')
                            ->label('住所')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('image_path')
                            ->label('店舗画像')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '16:9',
                            ])
                            ->imageResizeMode('force')
                            ->imageResizeTargetWidth(1920)
                            ->imageResizeTargetHeight(1080)
                            ->directory('stores')
                            ->disk('public')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'])
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('営業時間')
                    ->schema([
                        Forms\Components\Repeater::make('business_hours')
                            ->label('営業時間設定')
                            ->schema([
                                Forms\Components\Select::make('day')
                                    ->label('曜日')
                                    ->options([
                                        'monday' => '月曜日',
                                        'tuesday' => '火曜日',
                                        'wednesday' => '水曜日',
                                        'thursday' => '木曜日',
                                        'friday' => '金曜日',
                                        'saturday' => '土曜日',
                                        'sunday' => '日曜日',
                                    ])
                                    ->required()
                                    ->disabled()
                                    ->dehydrated(true),
                                Forms\Components\TextInput::make('open_time')
                                    ->label('開店時間')
                                    ->placeholder('09:00')
                                    ->mask('99:99')
                                    ->rules(['regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/'])
                                    ->helperText('例: 09:00'),
                                Forms\Components\TextInput::make('close_time')
                                    ->label('閉店時間')
                                    ->placeholder('18:00')
                                    ->mask('99:99')
                                    ->rules(['regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/'])
                                    ->helperText('例: 18:00'),
                                Forms\Components\Toggle::make('is_closed')
                                    ->label('定休日')
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, $set) {
                                        if ($state) {
                                            $set('open_time', null);
                                            $set('close_time', null);
                                        }
                                    }),
                            ])
                            ->columns(4)
                            ->defaultItems(7)
                            ->reorderable(false)
                            ->default([
                                ['day' => 'monday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
                                ['day' => 'tuesday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
                                ['day' => 'wednesday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
                                ['day' => 'thursday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
                                ['day' => 'friday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
                                ['day' => 'saturday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
                                ['day' => 'sunday', 'open_time' => null, 'close_time' => null, 'is_closed' => true],
                            ]),
                    ]),

                Forms\Components\Section::make('設定')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('reservation_slot_duration')
                                    ->label('予約枠の単位（分）')
                                    ->numeric()
                                    ->default(30)
                                    ->helperText('予約時間の最小単位'),
                                Forms\Components\TextInput::make('max_advance_days')
                                    ->label('予約受付期間（日）')
                                    ->numeric()
                                    ->default(30)
                                    ->helperText('何日先まで予約可能か'),
                                Forms\Components\TextInput::make('cancellation_deadline_hours')
                                    ->label('キャンセル期限（時間）')
                                    ->numeric()
                                    ->default(24)
                                    ->helperText('予約何時間前までキャンセル可能か'),
                                Forms\Components\Toggle::make('require_confirmation')
                                    ->label('予約確認が必要')
                                    ->default(false)
                                    ->helperText('スタッフの確認後に予約確定'),
                            ]),
                        Forms\Components\CheckboxList::make('payment_methods')
                            ->label('対応支払い方法')
                            ->options([
                                'cash' => '現金',
                                'credit_card' => 'クレジットカード',
                                'debit_card' => 'デビットカード',
                                'qr_payment' => 'QR決済（PayPay等）',
                                'e_money' => '電子マネー',
                                'bank_transfer' => '銀行振込',
                            ])
                            ->columns(2)
                            ->default(['cash', 'credit_card']),
                    ]),
                    
                Forms\Components\Section::make('予約受付設定')
                    ->description('オンライン予約の受付ルールを設定します')
                    ->schema([
                        Forms\Components\TextInput::make('min_booking_hours')
                            ->label('最短予約受付時間')
                            ->helperText('何時間前まで予約を受け付けるか（例：1 = 1時間前まで予約可能）')
                            ->numeric()
                            ->default(1)
                            ->minValue(0)
                            ->maxValue(72)
                            ->suffix('時間前')
                            ->required(),
                        Forms\Components\Toggle::make('allow_same_day_booking')
                            ->label('当日予約を許可')
                            ->helperText('チェックを外すと当日予約ができなくなります')
                            ->default(true)
                            ->inline(false),
                        Forms\Components\TextInput::make('max_advance_days')
                            ->label('最大予約受付日数')
                            ->helperText('何日先まで予約を受け付けるか')
                            ->numeric()
                            ->default(90)
                            ->minValue(1)
                            ->maxValue(365)
                            ->suffix('日先まで')
                            ->required(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('店舗名')
                    ->searchable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('店舗コード')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('電話番号')
                    ->searchable(),
                Tables\Columns\TextColumn::make('address')
                    ->label('住所')
                    ->limit(30)
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('状態')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'inactive',
                        'danger' => 'closed',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => '営業中',
                        'inactive' => '休業中',
                        'closed' => '閉店',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('menus_count')
                    ->label('メニュー数')
                    ->counts('menus')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('登録日')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('状態')
                    ->options([
                        'active' => '営業中',
                        'inactive' => '休業中',
                        'closed' => '閉店',
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStores::route('/'),
            'create' => Pages\CreateStore::route('/create'),
            'view' => Pages\ViewStore::route('/{record}'),
            'edit' => Pages\EditStore::route('/{record}/edit'),
        ];
    }
}