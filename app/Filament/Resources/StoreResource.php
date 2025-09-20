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
                                Forms\Components\Section::make('予約用リンク')
                                    ->schema([
                                        Forms\Components\Placeholder::make('reservation_links')
                                            ->label('')
                                            ->content(function ($record) {
                                                if (!$record || !$record->id) {
                                                    return '保存後にリンクが生成されます';
                                                }

                                                $baseUrl = config('app.url', 'https://reservation.meno-training.com');
                                                $linkById = $baseUrl . '/stores?store_id=' . $record->id;
                                                $linkBySlug = $baseUrl . '/stores?store=' . urlencode(strtolower(str_replace(' ', '-', $record->name)));

                                                return new \Illuminate\Support\HtmlString("
                                                    <div class='space-y-4'>
                                                        <div>
                                                            <p class='text-sm font-medium text-gray-700 mb-2'>IDを使用したリンク（推奨）</p>
                                                            <div class='flex items-center space-x-2'>
                                                                <input
                                                                    type='text'
                                                                    value='{$linkById}'
                                                                    id='link-by-id'
                                                                    readonly
                                                                    class='flex-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm'
                                                                />
                                                                <button
                                                                    type='button'
                                                                    onclick='copyToClipboard(\"link-by-id\")'
                                                                    class='px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm'
                                                                >
                                                                    コピー
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <div>
                                                            <p class='text-sm font-medium text-gray-700 mb-2'>店舗名を使用したリンク</p>
                                                            <div class='flex items-center space-x-2'>
                                                                <input
                                                                    type='text'
                                                                    value='{$linkBySlug}'
                                                                    id='link-by-slug'
                                                                    readonly
                                                                    class='flex-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm'
                                                                />
                                                                <button
                                                                    type='button'
                                                                    onclick='copyToClipboard(\"link-by-slug\")'
                                                                    class='px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm'
                                                                >
                                                                    コピー
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <script>
                                                            function copyToClipboard(elementId) {
                                                                const input = document.getElementById(elementId);
                                                                input.select();
                                                                document.execCommand('copy');

                                                                // フィードバック表示
                                                                const button = input.nextElementSibling;
                                                                const originalText = button.innerText;
                                                                button.innerText = 'コピーしました！';
                                                                button.classList.add('bg-green-600', 'hover:bg-green-700');
                                                                button.classList.remove('bg-blue-600', 'hover:bg-blue-700');

                                                                setTimeout(() => {
                                                                    button.innerText = originalText;
                                                                    button.classList.remove('bg-green-600', 'hover:bg-green-700');
                                                                    button.classList.add('bg-blue-600', 'hover:bg-blue-700');
                                                                }, 2000);
                                                            }
                                                        </script>
                                                    </div>
                                                ");
                                            })
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->collapsed(fn ($record) => !$record || !$record->id),

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
                                        Forms\Components\FileUpload::make('image_path')
                                            ->label('店舗画像')
                                            ->image()
                                            ->imageEditor()
                                            ->imageEditorAspectRatios([
                                                '16:9',
                                                '4:3',
                                                '1:1',
                                            ])
                                            ->directory('stores')
                                            ->disk('public')
                                            ->visibility('public')
                                            ->acceptedFileTypes(['image/jpeg', 'image/jpg', 'image/png', 'image/webp'])
                                            ->maxSize(5120) // 5MB
                                            ->columnSpanFull(),
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
                                Forms\Components\Select::make('reservation_slot_duration')
                                    ->label('予約枠の長さ（分）')
                                    ->options([
                                        15 => '15分間隔',
                                        30 => '30分間隔',
                                        60 => '60分間隔',
                                    ])
                                    ->default(30)
                                    ->required()
                                    ->helperText('予約可能な時間間隔を選択してください'),
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

                        Forms\Components\Tabs\Tab::make('カルテ設定')
                            ->schema([
                                Forms\Components\Section::make('支払い方法設定')
                                    ->description('この店舗で利用可能な支払い方法を設定してください')
                                    ->schema([
                                        Forms\Components\Repeater::make('payment_methods')
                                            ->label('支払い方法')
                                            ->schema([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('支払い方法名')
                                                    ->placeholder('現金')
                                                    ->required(),
                                            ])
                                            ->defaultItems(3)
                                            ->default([
                                                ['name' => '現金'],
                                                ['name' => 'クレジットカード'],
                                                ['name' => 'サブスク'],
                                            ])
                                            ->addActionLabel('支払い方法を追加')
                                            ->collapsible(),
                                    ]),
                                    
                                Forms\Components\Section::make('来店経路設定')
                                    ->description('顧客の来店経路として表示する選択肢を設定してください')
                                    ->schema([
                                        Forms\Components\Repeater::make('visit_sources')
                                            ->label('来店経路')
                                            ->schema([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('来店経路名')
                                                    ->placeholder('ホームページ')
                                                    ->required(),
                                            ])
                                            ->defaultItems(4)
                                            ->default([
                                                ['name' => 'ホームページ'],
                                                ['name' => '電話'],
                                                ['name' => 'LINE'],
                                                ['name' => '紹介'],
                                            ])
                                            ->addActionLabel('来店経路を追加')
                                            ->collapsible(),
                                    ]),
                            ]),

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
                                            ->content('この方式では、シフト管理で登録されたスタッフの出勤人数に応じて、タイムラインが表示されます。'),
                                        
                                        Forms\Components\TextInput::make('shift_based_capacity')
                                            ->label('実際の予約可能席数')
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->minValue(1)
                                            ->maxValue(10)
                                            ->helperText('機械台数など物理的制約による上限。スタッフが何人いても、この数が予約可能枠の上限になります。'),
                                        
                                        Forms\Components\Placeholder::make('capacity_info')
                                            ->label('')
                                            ->content(fn ($get) => 
                                                '設備制約: ' . ($get('shift_based_capacity') ?? 1) . '席 + サブライン: 1席（臨時対応用）'
                                            ),
                                        
                                        Forms\Components\Placeholder::make('staff_example')
                                            ->label('動作例')
                                            ->content(fn ($get) => 
                                                'スタッフ3人出勤 + 設備制約' . ($get('shift_based_capacity') ?? 1) . '席 → 実際の予約枠は' . ($get('shift_based_capacity') ?? 1) . '席'
                                            ),
                                        
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
                                    ->description('店舗専用のLINE公式アカウントの設定を行います。')
                                    ->schema([
                                        Forms\Components\Placeholder::make('line_setup_guide')
                                            ->label('')
                                            ->content(new \Illuminate\Support\HtmlString('
                                                <div class="space-y-3 text-sm">
                                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                                        <h4 class="font-semibold text-blue-900 mb-2">📋 LINE設定の流れ</h4>
                                                        <ol class="list-decimal list-inside space-y-1 text-blue-700">
                                                            <li>LINE Developersコンソールでチャネルを作成</li>
                                                            <li>Messaging APIを有効化</li>
                                                            <li>Channel Access TokenとChannel Secretを取得</li>
                                                            <li>Webhook URLを設定: <code class="bg-gray-100 px-1">https://your-domain.com/api/line/webhook/{store_code}</code></li>
                                                            <li>下記フォームに情報を入力</li>
                                                        </ol>
                                                    </div>
                                                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                                                        <h4 class="font-semibold text-amber-900 mb-2">⚠️ 重要な注意事項</h4>
                                                        <ul class="list-disc list-inside space-y-1 text-amber-700">
                                                            <li>各店舗ごとに個別のLINE公式アカウントが必要です</li>
                                                            <li>無料プランでは月1,000通まで送信可能です</li>
                                                            <li>Channel Access Tokenは定期的に更新が必要です（最長2年）</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            ')),
                                        
                                        Forms\Components\Toggle::make('line_enabled')
                                            ->label('LINE連携を有効にする')
                                            ->helperText('有効にすると、顧客へのLINE通知機能が利用可能になります')
                                            ->reactive(),
                                        
                                        Forms\Components\TextInput::make('line_official_account_id')
                                            ->label('LINE公式アカウントID')
                                            ->placeholder('@ginza_eye_training')
                                            ->helperText('LINE公式アカウントの@で始まるID（友だち追加時に使用）')
                                            ->visible(fn ($get) => $get('line_enabled')),
                                        
                                        Forms\Components\TextInput::make('line_bot_basic_id')
                                            ->label('Bot Basic ID')
                                            ->placeholder('@123abcde')
                                            ->helperText('LINE Developersコンソールで確認できるBot固有のID')
                                            ->live()
                                            ->afterStateUpdated(function ($state, $set) {
                                                // 値を確実に保存
                                                if (!empty($state)) {
                                                    $set('line_bot_basic_id', $state);
                                                }
                                            })
                                            ->visible(fn ($get) => $get('line_enabled')),
                                        
                                        Forms\Components\Textarea::make('line_channel_access_token')
                                            ->label('Channel Access Token')
                                            ->rows(3)
                                            ->helperText('LINE Messaging APIのアクセストークン（長期トークンを推奨）')
                                            ->visible(fn ($get) => $get('line_enabled')),
                                        
                                        Forms\Components\TextInput::make('line_channel_secret')
                                            ->label('Channel Secret')
                                            ->password()
                                            ->helperText('Webhook署名検証用のシークレットキー')
                                            ->visible(fn ($get) => $get('line_enabled')),
                                        
                                        Forms\Components\TextInput::make('line_liff_id')
                                            ->label('LIFF ID')
                                            ->placeholder('1234567890-AbCdEfGh')
                                            ->helperText('LINE Front-end Framework ID（LINE Developersコンソールで作成）')
                                            ->visible(fn ($get) => $get('line_enabled')),
                                        
                                        Forms\Components\TextInput::make('line_add_friend_url')
                                            ->label('友だち追加URL')
                                            ->url()
                                            ->placeholder('https://lin.ee/xxxxx')
                                            ->helperText('LINE公式アカウントマネージャーで取得できる友だち追加用のURL')
                                            ->visible(fn ($get) => $get('line_enabled')),
                                    ])
                                    ->columns(1),

                                Forms\Components\Section::make('送信設定')
                                    ->description('どのタイミングで顧客にLINE通知を送信するかを設定します')
                                    ->schema([
                                        Forms\Components\Placeholder::make('notification_flow_guide')
                                            ->label('')
                                            ->content(new \Illuminate\Support\HtmlString('
                                                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-sm">
                                                    <h4 class="font-semibold text-green-900 mb-2">📱 通知の優先順位</h4>
                                                    <ol class="list-decimal list-inside space-y-1 text-green-700">
                                                        <li><strong>LINE通知</strong>を最優先で送信</li>
                                                        <li>LINE送信失敗またはLINE未連携の場合は<strong>SMS送信</strong></li>
                                                        <li>両方失敗した場合は管理者にエラー通知</li>
                                                    </ol>
                                                    <p class="mt-2 text-green-600">※ 顧客のLINE連携は予約完了画面のQRコードから行われます</p>
                                                </div>
                                            ')),
                                        
                                        Forms\Components\Toggle::make('line_send_reservation_confirmation')
                                            ->label('予約確認を送信')
                                            ->helperText('予約完了時に自動で確認メッセージを送信')
                                            ->default(true),
                                        
                                        Forms\Components\Toggle::make('line_send_reminder')
                                            ->label('リマインダーを送信')
                                            ->helperText('予約日の前日に自動でリマインダーを送信')
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
                                        
                                        Forms\Components\Textarea::make('line_followup_message_7days')
                                            ->label('7日後フォローアップ')
                                            ->rows(4)
                                            ->default("{{customer_name}}様\n\n前回のご来店から1週間が経ちました。\n目の調子はいかがでしょうか？\n\n次回のご予約はこちらから\n{{store_name}}"),
                                        
                                        Forms\Components\Textarea::make('line_followup_message_15days')
                                            ->label('15日後フォローアップ')
                                            ->rows(4)
                                            ->default("{{customer_name}}様\n\n前回のご来店から2週間が経ちました。\n目の調子はいかがでしょうか？\n\n定期的なケアで効果を持続させませんか？\n{{store_name}}"),
                                        
                                        Forms\Components\Textarea::make('line_followup_message_30days')
                                            ->label('30日後フォローアップ（従来）')
                                            ->rows(4)
                                            ->default("{{customer_name}}様\n\n前回のご来店から1ヶ月が経ちました。\n目の調子はいかがでしょうか？\n\n次回のご予約はこちらから\n{{store_name}}"),
                                        
                                        Forms\Components\Textarea::make('line_followup_message_60days')
                                            ->label('60日後フォローアップ（従来）')
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