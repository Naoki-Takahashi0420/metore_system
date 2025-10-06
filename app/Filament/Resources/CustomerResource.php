<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use App\Services\CustomerMergeService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = '顧客管理';

    protected static ?string $modelLabel = '顧客';

    protected static ?string $pluralModelLabel = '顧客';
    
    protected static ?int $navigationSort = 7;

    /**
     * グローバル検索の対象カラムを定義
     */
    protected static ?array $globallySearchableAttributes = ['last_name', 'first_name', 'phone', 'email'];

    public static function getGlobalSearchEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['reservations' => function ($query) {
            $query->with('store');
        }]);
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->last_name . ' ' . $record->first_name;
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            '電話番号' => $record->phone,
            'メール' => $record->email ?? '-',
            '予約数' => $record->reservations->count() . '件',
        ];
    }

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

                Forms\Components\Section::make('スタッフ用情報')
                    ->description('顧客には表示されない、スタッフ間で共有する情報')
                    ->schema([
                        Forms\Components\Textarea::make('characteristics')
                            ->label('顧客特性・メモ')
                            ->placeholder('例：ドライアイ気味、丁寧な説明を好む、施術は優しめに、紹介者情報など')
                            ->rows(4)
                            ->helperText('接客時の注意点、施術の好み、体質、紹介者情報などをメモしてください')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('通知設定')
                    ->description('顧客への通知方法と連携状態を管理します')
                    ->schema([
                        Forms\Components\Placeholder::make('line_status_info')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record) return '';
                                
                                $lineStatus = $record->isLinkedToLine() 
                                    ? '<span class="text-green-600 font-semibold">✅ LINE連携済み</span>' 
                                    : '<span class="text-gray-500">⚪ LINE未連携</span>';
                                
                                $linkedDate = $record->line_linked_at 
                                    ? ' (連携日: ' . $record->line_linked_at->format('Y年n月j日') . ')'
                                    : '';
                                
                                $explanation = !$record->isLinkedToLine() 
                                    ? '<p class="mt-2 text-sm text-gray-600">💡 顧客のLINE連携は予約完了画面で表示されるQRコードから行われます。<br>連携後は自動的にLINE通知が優先されます。</p>'
                                    : '';
                                
                                return new \Illuminate\Support\HtmlString(
                                    '<div class="bg-gray-50 rounded-lg p-4">
                                        <h4 class="font-semibold mb-2">LINE連携状態: ' . $lineStatus . $linkedDate . '</h4>
                                        <div class="text-sm space-y-2">
                                            <p>📱 通知優先順位:</p>
                                            <ol class="list-decimal list-inside ml-4">
                                                <li>LINE通知（連携済みの場合）</li>
                                                <li>SMS通知（LINE失敗時または未連携時）</li>
                                            </ol>
                                            ' . $explanation . '
                                        </div>
                                    </div>'
                                );
                            })
                            ->columnSpanFull(),
                        
                        Forms\Components\Toggle::make('line_notifications_enabled')
                            ->label('LINE通知を受け取る')
                            ->default(true)
                            ->helperText('LINE連携済みの場合、予約確認やリマインダーをLINEで受信')
                            ->disabled(fn ($record) => !$record || !$record->isLinkedToLine()),
                        
                        Forms\Components\Toggle::make('sms_notifications_enabled')
                            ->label('SMS通知を受け取る')
                            ->default(true)
                            ->helperText('LINE未連携またはLINE送信失敗時にSMSで通知'),
                        
                        Forms\Components\TextInput::make('line_user_id')
                            ->label('LINE User ID')
                            ->disabled()
                            ->helperText('システムが自動管理するID')
                            ->visible(fn ($record) => $record && $record->isLinkedToLine()),
                        
                        Forms\Components\Toggle::make('is_blocked')
                            ->label('要注意顧客')
                            ->default(false)
                            ->helperText('問題のある顧客としてマーク（通知は送信されません）')
                            ->columnSpan(1),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('サブスク契約')
                    ->schema([
                        Forms\Components\Placeholder::make('subscription_info')
                            ->label('契約中のサブスク')
                            ->content(function ($record) {
                                if (!$record || !$record->subscriptions->count()) {
                                    return 'サブスク契約なし';
                                }
                                
                                $html = '<div class="space-y-3">';
                                foreach ($record->subscriptions as $sub) {
                                    $statusClass = 'text-gray-600';
                                    $statusText = '正常';
                                    
                                    if ($sub->payment_failed) {
                                        $statusClass = 'text-red-600 font-bold';
                                        $statusText = '決済失敗';
                                    } elseif ($sub->is_paused) {
                                        $statusClass = 'text-yellow-600 font-bold';
                                        $statusText = '休止中';
                                    } elseif ($sub->isEndingSoon()) {
                                        $statusClass = 'text-orange-600';
                                        $statusText = '終了間近';
                                    }
                                    
                                    $storeName = $sub->store ? $sub->store->name : '店舗未設定';
                                    
                                    $html .= '<div class="bg-gray-50 border rounded-lg p-4">';
                                    $html .= '<div class="grid grid-cols-2 gap-4">';
                                    
                                    // 左側：基本情報
                                    $html .= '<div>';
                                    $html .= '<p class="font-semibold text-lg mb-2">' . $sub->plan_name . '</p>';
                                    $html .= '<p class="text-sm text-gray-600">店舗: ' . $storeName . '</p>';
                                    $html .= '<p class="text-sm text-gray-600">月額: ¥' . number_format($sub->monthly_price) . '</p>';
                                    $html .= '<p class="text-sm text-gray-600">利用制限: ' . ($sub->monthly_limit ? $sub->monthly_limit . '回/月' : '無制限') . '</p>';
                                    $html .= '<p class="text-sm text-gray-600">今月利用: ' . $sub->current_month_visits . '回</p>';
                                    $html .= '</div>';
                                    
                                    // 右側：ステータスと日付
                                    $html .= '<div class="text-right">';
                                    $html .= '<p class="' . $statusClass . ' text-lg mb-2">' . $statusText . '</p>';
                                    
                                    if ($sub->billing_start_date) {
                                        $html .= '<p class="text-sm text-gray-600">開始日: ' . $sub->billing_start_date->format('Y年m月d日') . '</p>';
                                    }
                                    if ($sub->end_date) {
                                        $html .= '<p class="text-sm text-gray-600">終了日: ' . $sub->end_date->format('Y年m月d日') . '</p>';
                                    }
                                    
                                    // ステータス詳細
                                    if ($sub->payment_failed) {
                                        $html .= '<p class="text-sm text-red-600 mt-2">理由: ' . ($sub->payment_failed_reason_display ?? '不明') . '</p>';
                                        if ($sub->payment_failed_at) {
                                            $html .= '<p class="text-sm text-red-600">発生日: ' . $sub->payment_failed_at->format('Y年m月d日') . '</p>';
                                        }
                                    }
                                    if ($sub->is_paused) {
                                        $html .= '<p class="text-sm text-yellow-600 mt-2">休止期間: ' . $sub->pause_end_date->format('Y年m月d日') . 'まで</p>';
                                    }
                                    $html .= '</div>';
                                    $html .= '</div>';
                                    
                                    // メモ欄（サブスクのメモと決済失敗メモを統合表示）
                                    $notes = [];
                                    if ($sub->notes) {
                                        $notes[] = $sub->notes;
                                    }
                                    if ($sub->payment_failed_notes) {
                                        $notes[] = '【決済関連】' . $sub->payment_failed_notes;
                                    }
                                    
                                    if (!empty($notes)) {
                                        $html .= '<div class="mt-3 pt-3 border-t">';
                                        $html .= '<p class="text-sm font-semibold text-gray-700">サブスクメモ:</p>';
                                        $html .= '<p class="text-sm text-gray-600 mt-1">' . nl2br(implode("\n", $notes)) . '</p>';
                                        $html .= '</div>';
                                    }
                                    
                                    $html .= '</div>';
                                }
                                $html .= '</div>';
                                
                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpanFull(),
                        
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('manage_subscription')
                                ->label('サブスク管理画面へ')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url(fn ($record) => $record && $record->subscriptions->count() 
                                    ? route('filament.admin.resources.subscriptions.edit', $record->subscriptions->first())
                                    : route('filament.admin.resources.subscriptions.index'))
                                ->openUrlInNewTab(),
                        ]),
                        
                        // 新規サブスク契約追加
                        Forms\Components\Repeater::make('subscriptions')
                            ->relationship('subscriptions')
                            ->label('新規サブスク契約追加')
                            ->visible(fn ($operation) => $operation === 'edit' || $operation === 'view')
                            ->schema([
                                Forms\Components\Select::make('store_id')
                                    ->label('店舗')
                                    ->options(function () {
                                        return \App\Models\Store::where('is_active', true)
                                            ->pluck('name', 'id');
                                    })
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        $set('menu_id', null); // 店舗変更時にメニュー選択をリセット
                                    }),
                                Forms\Components\Select::make('menu_id')
                                    ->label('サブスクメニュー')
                                    ->options(function (Forms\Get $get) {
                                        $storeId = $get('store_id');
                                        if (!$storeId) {
                                            return [];
                                        }
                                        return \App\Models\Menu::where('store_id', $storeId)
                                            ->where('is_subscription', true)
                                            ->where('is_available', true)
                                            ->pluck('name', 'id');
                                    })
                                    ->required()
                                    ->reactive()
                                    ->disabled(fn (Forms\Get $get) => !$get('store_id'))
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $menu = \App\Models\Menu::find($state);
                                            if ($menu) {
                                                $set('plan_name', $menu->name);
                                                $set('plan_type', 'MENU_' . $menu->id);
                                                // subscription_monthly_priceがない場合は通常のpriceを使用
                                                $monthlyPrice = $menu->subscription_monthly_price ?? $menu->price ?? 0;
                                                $set('monthly_price', $monthlyPrice);
                                                $set('monthly_limit', $menu->max_monthly_usage ?? null);
                                                $set('contract_months', $menu->contract_months ?? 12);
                                            }
                                        }
                                    }),
                                Forms\Components\Hidden::make('plan_name')
                                    ->default(''),
                                Forms\Components\Hidden::make('plan_type')
                                    ->default(''),
                                Forms\Components\DatePicker::make('billing_start_date')
                                    ->label('課金開始日')
                                    ->required()
                                    ->default(now()),
                                Forms\Components\DatePicker::make('service_start_date')
                                    ->label('施術開始日')
                                    ->required()
                                    ->default(now())
                                    ->reactive()
                                    ->helperText('サブスク限定メニューが利用可能になる日')
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        if ($state && $get('contract_months')) {
                                            $endDate = \Carbon\Carbon::parse($state)
                                                ->addMonths($get('contract_months'));
                                            $set('end_date', $endDate->format('Y-m-d'));
                                        }
                                    }),
                                Forms\Components\TextInput::make('contract_months')
                                    ->label('契約期間')
                                    ->numeric()
                                    ->suffix('ヶ月')
                                    ->default(12)
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        if ($state && $get('service_start_date')) {
                                            $contractMonths = (int) $state; // 文字列を整数に変換
                                            $endDate = \Carbon\Carbon::parse($get('service_start_date'))
                                                ->addMonths($contractMonths);
                                            $set('end_date', $endDate->format('Y-m-d'));
                                        }
                                    }),
                                Forms\Components\DatePicker::make('end_date')
                                    ->label('契約終了日')
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText('サービス開始日と契約期間から自動計算'),
                                Forms\Components\TextInput::make('monthly_price')
                                    ->label('月額料金')
                                    ->numeric()
                                    ->prefix('¥')
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0),
                                Forms\Components\TextInput::make('monthly_limit')
                                    ->label('月間利用回数')
                                    ->numeric()
                                    ->suffix('回')
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0)
                                    ->helperText('空欄の場合は無制限'),
                                Forms\Components\Select::make('status')
                                    ->label('状態')
                                    ->options([
                                        'active' => '有効',
                                        'paused' => '一時停止',
                                        'cancelled' => '解約済み',
                                    ])
                                    ->default('active')
                                    ->required(),
                                Forms\Components\Textarea::make('notes')
                                    ->label('メモ')
                                    ->rows(2)
                                    ->placeholder('例：初月無料キャンペーン適用')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('サブスク契約を追加')
                            ->collapsible()
                            ->collapsed()
                            ->cloneable()
                            ->deleteAction(
                                fn (Forms\Components\Actions\Action $action) => $action->requiresConfirmation(),
                            ),
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
                Tables\Columns\IconColumn::make('has_subscription')
                    ->label('サブスク')
                    ->getStateUsing(fn ($record) => $record->hasActiveSubscription())
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('店舗')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('顧客名')
                    ->formatStateUsing(function ($record) {
                        $name = $record->last_name . ' ' . $record->first_name;
                        if ($record->isHighRisk()) {
                            $riskLevel = $record->getRiskLevel();
                            $icon = match($riskLevel) {
                                'high' => '⚠️',
                                'medium' => '⚡',
                                default => ''
                            };
                            $details = [];
                            if ($record->cancellation_count > 0) {
                                $details[] = "キャンセル{$record->cancellation_count}回";
                            }
                            if ($record->no_show_count > 0) {
                                $details[] = "来店なし{$record->no_show_count}回";
                            }
                            if ($record->change_count >= 3) {
                                $details[] = "変更{$record->change_count}回";
                            }
                            $detailText = implode('/', $details);
                            return "{$icon} {$name} ({$detailText})";
                        }
                        return $name;
                    })
                    ->searchable(query: function ($query, $search) {
                        $dbDriver = \DB::connection()->getDriverName();

                        // 検索文字列をトリム
                        $search = trim($search);

                        if ($dbDriver === 'mysql') {
                            // MySQLの場合：CONCAT関数を使用
                            return $query->where(function ($q) use ($search) {
                                $q->where('last_name', 'like', "%{$search}%")
                                  ->orWhere('first_name', 'like', "%{$search}%")
                                  ->orWhere('last_name_kana', 'like', "%{$search}%")
                                  ->orWhere('first_name_kana', 'like', "%{$search}%")
                                  ->orWhereRaw('CONCAT(last_name, first_name) LIKE ?', ["%{$search}%"])
                                  ->orWhereRaw('CONCAT(last_name, " ", first_name) LIKE ?', ["%{$search}%"])
                                  ->orWhereRaw('CONCAT(last_name_kana, first_name_kana) LIKE ?', ["%{$search}%"])
                                  ->orWhereRaw('CONCAT(last_name_kana, " ", first_name_kana) LIKE ?', ["%{$search}%"]);
                            });
                        } else {
                            // SQLiteの場合：|| 演算子を使用
                            return $query->where(function ($q) use ($search) {
                                $q->where('last_name', 'like', "%{$search}%")
                                  ->orWhere('first_name', 'like', "%{$search}%")
                                  ->orWhere('last_name_kana', 'like', "%{$search}%")
                                  ->orWhere('first_name_kana', 'like', "%{$search}%")
                                  ->orWhereRaw('(last_name || first_name) LIKE ?', ["%{$search}%"])
                                  ->orWhereRaw('(last_name || " " || first_name) LIKE ?', ["%{$search}%"])
                                  ->orWhereRaw('(last_name_kana || first_name_kana) LIKE ?', ["%{$search}%"])
                                  ->orWhereRaw('(last_name_kana || " " || first_name_kana) LIKE ?', ["%{$search}%"]);
                            });
                        }
                    })
                    ->tooltip(function ($record) {
                        if (!$record->isHighRisk()) {
                            return null;
                        }
                        $details = [];
                        if ($record->cancellation_count > 0) {
                            $details[] = "キャンセル回数: {$record->cancellation_count}回";
                        }
                        if ($record->no_show_count > 0) {
                            $details[] = "来店なし回数: {$record->no_show_count}回";
                        }
                        if ($record->change_count > 0) {
                            $details[] = "予約変更回数: {$record->change_count}回";
                        }
                        if ($record->last_cancelled_at) {
                            $details[] = "最終キャンセル: " . $record->last_cancelled_at->format('Y/m/d');
                        }
                        return implode("\n", $details);
                    }),
                Tables\Columns\TextColumn::make('phone')
                    ->label('電話番号')
                    ->searchable(query: function ($query, $search) {
                        // 数字が含まれている場合のみ電話番号検索を実行
                        if (preg_match('/\d/', $search)) {
                            return $query->where(function ($q) use ($search) {
                                // ハイフンありなしの両方で検索
                                $searchPlain = preg_replace('/[^0-9]/', '', $search);
                                $q->where('phone', 'like', "%{$search}%");
                                if (!empty($searchPlain)) {
                                    $q->orWhere('phone', 'like', "%{$searchPlain}%");
                                }
                            });
                        }
                        // 数字が含まれていない場合は検索しない（何もマッチしない条件を返す）
                        return $query->whereRaw('1 = 0');
                    }),
                Tables\Columns\TextColumn::make('email')
                    ->label('メールアドレス')
                    ->searchable(query: function ($query, $search) {
                        // @が含まれている場合のみメールアドレス検索を実行
                        if (strpos($search, '@') !== false || preg_match('/[a-zA-Z]/', $search)) {
                            return $query->where('email', 'like', "%{$search}%");
                        }
                        // メールアドレスっぽくない場合は検索しない
                        return $query->whereRaw('1 = 0');
                    }),
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
                Tables\Columns\TextColumn::make('stores_count')
                    ->label('利用店舗数')
                    ->getStateUsing(function ($record) {
                        return $record->reservations()
                            ->distinct('store_id')
                            ->count('store_id');
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('cancellation_count')
                    ->label('キャンセル')
                    ->sortable(),
                Tables\Columns\TextColumn::make('change_count')
                    ->label('変更回数')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('risk_status')
                    ->label('ステータス')
                    ->getStateUsing(function ($record) {
                        if (!$record->isHighRisk()) {
                            return '通常';
                        }
                        return match($record->getRiskLevel()) {
                            'high' => '要注意(高)',
                            'medium' => '要注意',
                            default => '通常'
                        };
                    })
                    ->color(function ($state) {
                        return match($state) {
                            '要注意(高)' => 'danger',
                            '要注意' => 'warning',
                            default => 'success'
                        };
                    })
                    ->icon(function ($state) {
                        return match($state) {
                            '要注意(高)' => 'heroicon-o-exclamation-triangle',
                            '要注意' => 'heroicon-o-exclamation-circle',
                            default => null
                        };
                    }),
                Tables\Columns\TextColumn::make('latest_store')
                    ->label('最新利用店舗')
                    ->getStateUsing(function ($record) {
                        $latestReservation = $record->reservations()
                            ->with('store')
                            ->latest('reservation_date')
                            ->first();
                        return $latestReservation?->store?->name ?? '-';
                    })
                    ->toggleable(),
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
                Tables\Filters\Filter::make('high_risk')
                    ->label('要注意顧客')
                    ->query(fn ($query) => $query->where(function ($q) {
                        $q->where('cancellation_count', '>=', 1)
                          ->orWhere('no_show_count', '>=', 1)
                          ->orWhere('change_count', '>=', 3);
                    })),
                Tables\Filters\SelectFilter::make('risk_level')
                    ->label('リスクレベル')
                    ->options([
                        'high' => '高リスク（キャンセル3回以上/来店なし2回以上）',
                        'medium' => '中リスク（キャンセル1回以上/変更3回以上）',
                        'low' => '通常',
                    ])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;
                        if (!$value) return $query;
                        
                        return match($value) {
                            'high' => $query->where(function ($q) {
                                $q->where('cancellation_count', '>=', 3)
                                  ->orWhere('no_show_count', '>=', 2);
                            }),
                            'medium' => $query->where(function ($q) {
                                $q->where(function ($q2) {
                                    $q2->where('cancellation_count', '>=', 1)
                                       ->where('cancellation_count', '<', 3);
                                })->orWhere(function ($q2) {
                                    $q2->where('no_show_count', '=', 1);
                                })->orWhere('change_count', '>=', 3);
                            }),
                            'low' => $query->where('cancellation_count', 0)
                                          ->where('no_show_count', 0)
                                          ->where('change_count', '<', 3),
                            default => $query
                        };
                    }),
                Tables\Filters\Filter::make('has_subscription')
                    ->label('サブスク契約中')
                    ->query(fn ($query) => $query->whereHas('subscriptions', function ($q) {
                        $q->where('status', 'active')
                          ->where(function ($q2) {
                              $q2->where('service_start_date', '<=', now())
                                 ->orWhereNull('service_start_date');
                          })
                          ->where(function ($q3) {
                              $q3->where('end_date', '>=', now())
                                 ->orWhereNull('end_date');
                          });
                    })),
                Tables\Filters\Filter::make('subscription_expiring')
                    ->label('サブスク期限切れ間近（7日以内）')
                    ->query(fn ($query) => $query->whereHas('subscriptions', function ($q) {
                        $q->where('status', 'active')
                          ->whereNotNull('end_date')
                          ->whereBetween('end_date', [now(), now()->addDays(7)]);
                    })),
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
                Tables\Filters\SelectFilter::make('store')
                    ->label('利用店舗')
                    ->options(function () {
                        $user = auth()->user();
                        
                        if ($user->hasRole('super_admin')) {
                            return \App\Models\Store::where('is_active', true)->pluck('name', 'id');
                        } elseif ($user->hasRole('owner')) {
                            return $user->manageableStores()->where('is_active', true)->pluck('name', 'stores.id');
                        } else {
                            // 店長・スタッフは自店舗のみ
                            return $user->store ? collect([$user->store->id => $user->store->name]) : collect();
                        }
                    })
                    ->query(function ($query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereHas('reservations', function ($subQuery) use ($data) {
                                $subQuery->where('store_id', $data['value']);
                            });
                        }
                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('find_similar')
                    ->label('類似顧客を探す')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => $record->last_name . $record->first_name . 'さんの類似顧客検索')
                    ->modalDescription('同姓同名の顧客を検索し、統合可能な場合は統合処理を実行できます。')
                    ->modalSubmitActionLabel('検索実行')
                    ->action(function ($record) {
                        $mergeService = new CustomerMergeService();
                        $similarCustomers = $mergeService->findSimilarCustomers($record);

                        if (empty($similarCustomers)) {
                            Notification::make()
                                ->title('類似顧客なし')
                                ->body('同姓同名の顧客は見つかりませんでした。')
                                ->info()
                                ->send();
                            return;
                        }

                        // 類似顧客が見つかった場合の処理
                        session()->put('similar_customers_for_' . $record->id, $similarCustomers);

                        Notification::make()
                            ->title('類似顧客発見')
                            ->body(count($similarCustomers) . '件の類似顧客が見つかりました。統合アクションで詳細を確認してください。')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('dismiss_similar')
                    ->label('統合をスキップ')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn ($record) => session()->has('similar_customers_for_' . $record->id))
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => $record->last_name . $record->first_name . 'さんの類似顧客をスキップ')
                    ->modalDescription('類似顧客の統合をスキップします。後でもう一度「類似顧客を探す」を実行できます。')
                    ->modalSubmitActionLabel('スキップする')
                    ->action(function ($record) {
                        session()->forget('similar_customers_for_' . $record->id);
                        Notification::make()
                            ->title('統合をスキップしました')
                            ->body('類似顧客の統合をスキップしました。必要に応じて再度「類似顧客を探す」を実行してください。')
                            ->info()
                            ->send();
                    }),
                Tables\Actions\Action::make('merge_customers')
                    ->label('顧客統合')
                    ->icon('heroicon-o-arrows-pointing-in')
                    ->color('warning')
                    ->visible(fn ($record) => session()->has('similar_customers_for_' . $record->id))
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => $record->last_name . $record->first_name . 'さんの顧客統合')
                    ->modalDescription('類似顧客との統合プレビューを確認し、統合を実行してください。')
                    ->modalContent(function ($record) {
                        $similarCustomers = session()->get('similar_customers_for_' . $record->id, []);

                        if (empty($similarCustomers)) {
                            return view('filament.customer.no-similar-customers');
                        }

                        return view('filament.customer.merge-preview', [
                            'customer' => $record,
                            'similarCustomers' => $similarCustomers
                        ]);
                    })
                    ->form([
                        Forms\Components\Select::make('target_customer_id')
                            ->label('統合対象の顧客を選択')
                            ->options(function ($record) {
                                $similarCustomers = session()->get('similar_customers_for_' . $record->id, []);
                                $options = [];
                                foreach ($similarCustomers as $customer) {
                                    $options[$customer['id']] = $customer['name'] . ' (予約' . $customer['reservations_count'] . '件, 最終来店: ' . $customer['last_visit'] . ')';
                                }
                                return $options;
                            })
                            ->required()
                            ->native(false)
                            ->helperText('統合後は選択した顧客の情報が削除され、現在の顧客に統合されます。'),
                    ])
                    ->modalSubmitActionLabel('統合実行')
                    ->action(function ($record, array $data) {
                        $targetCustomerId = $data['target_customer_id'];
                        $targetCustomer = Customer::find($targetCustomerId);

                        if (!$targetCustomer) {
                            Notification::make()
                                ->title('エラー')
                                ->body('統合対象の顧客が見つかりません。')
                                ->danger()
                                ->send();
                            return;
                        }

                        try {
                            $mergeService = new CustomerMergeService();
                            $mergedCustomer = $mergeService->merge($record, $targetCustomer);

                            // セッションから類似顧客情報を削除
                            session()->forget('similar_customers_for_' . $record->id);

                            Notification::make()
                                ->title('統合完了')
                                ->body($record->last_name . $record->first_name . 'さんと' . $targetCustomer->last_name . $targetCustomer->first_name . 'さんの情報が統合されました。')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('統合エラー')
                                ->body('顧客統合処理中にエラーが発生しました: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
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
            RelationManagers\ImagesRelationManager::class,
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
    
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        return $user->hasRole(['super_admin', 'owner', 'manager', 'staff']);
    }
    
    public static function canView($record): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        
        // スーパーアドミンは全顧客を閲覧可能
        if ($user->hasRole('super_admin')) {
            return true;
        }
        
        // その他のロールは予約がある店舗に基づいて判断
        $storeIds = [];
        if ($user->hasRole('owner')) {
            $storeIds = $user->manageableStores()->pluck('stores.id')->toArray();
        } elseif ($user->hasRole(['manager', 'staff']) && $user->store_id) {
            $storeIds = [$user->store_id];
        }
        
        if (empty($storeIds)) {
            return false;
        }
        
        // 該当店舗で予約がある顧客のみ閲覧可能
        return $record->reservations()->whereIn('store_id', $storeIds)->exists();
    }
    
    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        return $user->hasRole(['super_admin', 'owner', 'manager', 'staff']);
    }
    
    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        // 顧客編集は顧客閲覧権限と同じロジック
        return static::canView($record);
    }
    
    public static function canDelete($record): bool
    {
        // 予約履歴がない顧客（インポートされた顧客）は管理者権限があれば削除可能
        if ($record->reservations()->count() === 0) {
            $user = auth()->user();
            return $user && $user->hasRole(['super_admin', 'owner']);
        }
        
        $user = auth()->user();
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        // 顧客削除はスーパーアドミンとオーナーのみ
        if ($user->hasRole('super_admin')) {
            return true;
        }
        
        if ($user->hasRole('owner')) {
            // 予約履歴がある場合は管理可能店舗の予約があるかチェック
            $manageableStoreIds = $user->manageableStores()->pluck('stores.id');
            return $record->reservations()
                ->whereIn('store_id', $manageableStoreIds)
                ->exists();
        }
        
        return false;
    }
    
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // スーパーアドミンは全顧客を表示
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // オーナーは管理可能店舗に関連する顧客のみ表示
        // 予約を通じて店舗と関連がある顧客を表示
        if ($user->hasRole('owner')) {
            $manageableStoreIds = $user->manageableStores()->pluck('stores.id')->toArray();
            return $query->whereHas('reservations', function ($q) use ($manageableStoreIds) {
                $q->whereIn('store_id', $manageableStoreIds);
            });
        }

        // 店長・スタッフは所属店舗に関連する顧客のみ表示
        // 予約を通じて店舗と関連がある顧客を表示
        if ($user->hasRole(['manager', 'staff'])) {
            if ($user->store_id) {
                return $query->whereHas('reservations', function ($q) use ($user) {
                    $q->where('store_id', $user->store_id);
                });
            }
            return $query->whereRaw('1 = 0');
        }

        return $query->whereRaw('1 = 0');
    }
}