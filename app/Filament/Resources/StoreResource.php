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
                Forms\Components\Tabs::make('店舗情報')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('基本情報')
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
                                                'hidden' => '非表示',
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
                                    ])
                                    ->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('営業時間')
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
                                            ->required(),
                                        Forms\Components\TimePicker::make('open_time')
                                            ->label('開店時間')
                                            ->required(),
                                        Forms\Components\TimePicker::make('close_time')
                                            ->label('閉店時間')
                                            ->required(),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(7),
                            ]),

                        Forms\Components\Tabs\Tab::make('予約設定')
                            ->schema([
                                Forms\Components\TextInput::make('reservation_slot_duration')
                                    ->label('予約枠の長さ（分）')
                                    ->numeric()
                                    ->default(30)
                                    ->required(),
                                Forms\Components\TextInput::make('max_advance_days')
                                    ->label('予約可能な最大日数')
                                    ->numeric()
                                    ->default(30)
                                    ->required(),
                                Forms\Components\TextInput::make('cancellation_deadline_hours')
                                    ->label('キャンセル期限（時間前）')
                                    ->numeric()
                                    ->default(24)
                                    ->required(),
                                Forms\Components\Toggle::make('require_confirmation')
                                    ->label('予約確認を必須にする')
                                    ->default(false),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('予約ライン設定')
                            ->schema([
                                Forms\Components\Section::make('ライン設定')
                                    ->schema([
                                        Forms\Components\TextInput::make('main_lines_count')
                                            ->label('本ライン数')
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->helperText('新規顧客が利用可能なメインライン数'),
                                        Forms\Components\TextInput::make('sub_lines_count')
                                            ->label('予備ライン数')
                                            ->numeric()
                                            ->default(0)
                                            ->helperText('既存顧客優先の予備ライン数'),
                                        Forms\Components\Toggle::make('use_staff_assignment')
                                            ->label('スタッフ指定制を使用')
                                            ->helperText('小山・新宿店などで使用'),
                                        Forms\Components\Toggle::make('use_equipment_management')
                                            ->label('機材管理を使用')
                                            ->helperText('機材数に制限がある場合'),
                                    ])
                                    ->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('LINE設定')
                            ->schema([
                                Forms\Components\Section::make('LINE API設定')
                                    ->description('店舗専用のLINE公式アカウントの設定')
                                    ->schema([
                                        Forms\Components\Toggle::make('line_enabled')
                                            ->label('LINE連携を有効にする')
                                            ->reactive(),
                                        
                                        Forms\Components\TextInput::make('line_official_account_id')
                                            ->label('LINE公式アカウントID')
                                            ->placeholder('@ginza_eye_training')
                                            ->helperText('@で始まるID')
                                            ->visible(fn ($get) => $get('line_enabled')),
                                        
                                        Forms\Components\Textarea::make('line_channel_access_token')
                                            ->label('Channel Access Token')
                                            ->rows(3)
                                            ->visible(fn ($get) => $get('line_enabled')),
                                        
                                        Forms\Components\TextInput::make('line_channel_secret')
                                            ->label('Channel Secret')
                                            ->password()
                                            ->visible(fn ($get) => $get('line_enabled')),
                                        
                                        Forms\Components\TextInput::make('line_add_friend_url')
                                            ->label('友だち追加URL')
                                            ->url()
                                            ->placeholder('https://lin.ee/xxxxx')
                                            ->visible(fn ($get) => $get('line_enabled')),
                                    ])
                                    ->columns(1),

                                Forms\Components\Section::make('送信設定')
                                    ->schema([
                                        Forms\Components\Toggle::make('line_send_reservation_confirmation')
                                            ->label('予約確認を送信')
                                            ->default(true),
                                        
                                        Forms\Components\Toggle::make('line_send_reminder')
                                            ->label('リマインダーを送信')
                                            ->default(true)
                                            ->reactive(),
                                        
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TimePicker::make('line_reminder_time')
                                                    ->label('リマインダー送信時刻')
                                                    ->default('10:00')
                                                    ->visible(fn ($get) => $get('line_send_reminder')),
                                                
                                                Forms\Components\TextInput::make('line_reminder_days_before')
                                                    ->label('何日前に送信')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->suffix('日前')
                                                    ->visible(fn ($get) => $get('line_send_reminder')),
                                            ]),
                                        
                                        Forms\Components\Toggle::make('line_send_followup')
                                            ->label('フォローアップを送信')
                                            ->default(true),
                                        
                                        Forms\Components\Toggle::make('line_send_promotion')
                                            ->label('プロモーション送信を許可')
                                            ->default(true),
                                    ])
                                    ->columns(2)
                                    ->visible(fn ($get) => $get('line_enabled')),

                                Forms\Components\Section::make('メッセージテンプレート')
                                    ->description('変数: {{customer_name}}, {{reservation_date}}, {{reservation_time}}, {{menu_name}}, {{store_name}}')
                                    ->schema([
                                        Forms\Components\Textarea::make('line_reservation_message')
                                            ->label('予約確認メッセージ')
                                            ->rows(4)
                                            ->default("{{customer_name}}様\n\nご予約ありがとうございます。\n日時: {{reservation_date}} {{reservation_time}}\nメニュー: {{menu_name}}\n\nお待ちしております。\n{{store_name}}"),
                                        
                                        Forms\Components\Textarea::make('line_reminder_message')
                                            ->label('リマインダーメッセージ')
                                            ->rows(4)
                                            ->default("{{customer_name}}様\n\n明日のご予約のお知らせです。\n日時: {{reservation_date}} {{reservation_time}}\n\nお気をつけてお越しください。\n{{store_name}}"),
                                        
                                        Forms\Components\Textarea::make('line_followup_message_30days')
                                            ->label('30日後フォローアップ')
                                            ->rows(4)
                                            ->default("{{customer_name}}様\n\n前回のご来店から1ヶ月が経ちました。\n目の調子はいかがでしょうか？\n\n次回のご予約はこちらから\n{{store_name}}"),
                                        
                                        Forms\Components\Textarea::make('line_followup_message_60days')
                                            ->label('60日後フォローアップ')
                                            ->rows(4)
                                            ->default("{{customer_name}}様\n\nしばらくお会いできておりませんが、お元気でしょうか？\n特別クーポンをご用意しました。\n\nご予約お待ちしております。\n{{store_name}}"),
                                    ])
                                    ->columns(1)
                                    ->visible(fn ($get) => $get('line_enabled')),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('店舗コード')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('店舗名')
                    ->searchable()
                    ->sortable(),
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
                        'gray' => 'hidden',
                    ])
                    ->formatStateUsing(fn ($state) => match($state) {
                        'active' => '営業中',
                        'inactive' => '休業中',
                        'closed' => '閉店',
                        'hidden' => '非表示',
                        default => $state,
                    }),
                Tables\Columns\IconColumn::make('line_enabled')
                    ->label('LINE')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
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
                        'hidden' => '非表示',
                    ]),
                Tables\Filters\TernaryFilter::make('line_enabled')
                    ->label('LINE連携'),
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