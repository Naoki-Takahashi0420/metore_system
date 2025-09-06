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
                                    ->required()
                                    ->helperText('予約可能な時間間隔（例：30分ごと）'),
                                Forms\Components\TextInput::make('max_advance_days')
                                    ->label('予約可能な最大日数')
                                    ->numeric()
                                    ->default(30)
                                    ->required()
                                    ->helperText('何日先まで予約を受け付けるか'),
                                Forms\Components\TextInput::make('cancellation_deadline_hours')
                                    ->label('キャンセル期限（時間前）')
                                    ->numeric()
                                    ->default(24)
                                    ->required()
                                    ->helperText('予約時刻の何時間前までキャンセル可能か'),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('予約管理方式')
                            ->schema([
                                Forms\Components\Section::make('予約受付方式の選択')
                                    ->description('店舗の規模や運営スタイルに合わせて、予約管理方式を選択してください')
                                    ->schema([
                                        Forms\Components\Radio::make('use_staff_assignment')
                                            ->label('予約管理方式')
                                            ->options([
                                                false => '営業時間ベース（シンプル）',
                                                true => 'スタッフシフトベース（詳細管理）',
                                            ])
                                            ->descriptions([
                                                false => '営業時間内で固定の予約枠数を設定。小規模店舗向け',
                                                true => 'スタッフの出勤状況に応じて予約枠が変動。中〜大規模店舗向け',
                                            ])
                                            ->default(false)
                                            ->reactive(),
                                    ]),
                                
                                Forms\Components\Section::make('営業時間ベースの設定')
                                    ->description('予約受付ラインの設定')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('main_lines_count')
                                                    ->label('メインライン数')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->required()
                                                    ->minValue(1)
                                                    ->maxValue(10)
                                                    ->reactive()
                                                    ->afterStateUpdated(function ($state, $get, $set) {
                                                        $total = ($state ?? 0) + ($get('sub_lines_count') ?? 0);
                                                        $set('capacity', $total);
                                                    })
                                                    ->helperText('新規・既存顧客が利用できる基本ライン'),
                                                Forms\Components\Hidden::make('sub_lines_count')
                                                    ->default(1)
                                                    ->dehydrated(),
                                            ]),
                                        Forms\Components\Placeholder::make('total_capacity')
                                            ->label('')
                                            ->content(fn ($get) => 
                                                '公開予約枠: ' . ($get('main_lines_count') ?? 1) . '席 + サブライン: 1席（内部管理用）'
                                            ),
                                        Forms\Components\Hidden::make('capacity')
                                            ->default(fn ($get) => ($get('main_lines_count') ?? 1) + 1), // サブライン1を加算
                                    ])
                                    ->visible(fn ($get) => !$get('use_staff_assignment')),
                                
                                Forms\Components\Section::make('スタッフシフトベースの設定')
                                    ->description('スタッフ管理と連動した予約管理')
                                    ->schema([
                                        Forms\Components\Placeholder::make('shift_info')
                                            ->content('この方式では、シフト管理で登録されたスタッフの出勤人数に応じて、自動的に予約可能枠が決まります。'),
                                        Forms\Components\Placeholder::make('staff_example')
                                            ->label('')
                                            ->content('例：10時に3人出勤 → 10時の予約枠は3件まで受付可能'),
                                        Forms\Components\Hidden::make('main_lines_count')
                                            ->default(1),
                                        Forms\Components\Hidden::make('sub_lines_count')
                                            ->default(1), // スタッフベースもサブライン1固定
                                        Forms\Components\Hidden::make('capacity')
                                            ->default(1),
                                    ])
                                    ->visible(fn ($get) => $get('use_staff_assignment')),
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
    
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        return $user->hasRole(['super_admin', 'owner', 'manager']);
    }
    
    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        return $user->hasRole(['super_admin']);
    }
    
    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        // スーパーアドミンは全店舗編集可能
        if ($user->hasRole('super_admin')) {
            return true;
        }
        
        // オーナーは管理可能店舗のみ編集可能
        if ($user->hasRole('owner')) {
            return $user->manageableStores()->where('stores.id', $record->id)->exists();
        }
        
        // 店長は自分の店舗のみ編集可能
        if ($user->hasRole('manager')) {
            return $user->store_id === $record->id;
        }
        
        return false;
    }
    
    public static function canDelete($record): bool
    {
        $user = auth()->user();
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        // 店舗削除はスーパーアドミンのみ
        return $user->hasRole('super_admin');
    }
    
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        
        if (!$user || !$user->roles()->exists()) {
            return $query->whereRaw('1 = 0');
        }
        
        // スーパーアドミンは全店舗表示
        if ($user->hasRole('super_admin')) {
            return $query;
        }
        
        // オーナーは管理可能店舗のみ表示
        if ($user->hasRole('owner')) {
            $manageableStoreIds = $user->manageableStores()->pluck('stores.id');
            return $query->whereIn('id', $manageableStoreIds);
        }
        
        // 店長は自分の店舗のみ表示
        if ($user->hasRole('manager')) {
            return $query->where('id', $user->store_id);
        }
        
        // 権限がない場合は空のクエリ
        return $query->whereRaw('1 = 0');
    }
}