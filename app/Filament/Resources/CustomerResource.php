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
                        Forms\Components\Repeater::make('subscriptions')
                            ->relationship('subscriptions')
                            ->label('契約中のサブスク')
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
                                                $set('monthly_price', $menu->subscription_monthly_price);
                                                $set('monthly_limit', $menu->max_monthly_usage);
                                                $set('contract_months', $menu->default_contract_months ?? 3);
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
                                    ->default(now())
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        if ($state && $get('contract_months')) {
                                            $endDate = \Carbon\Carbon::parse($state)
                                                ->addMonths($get('contract_months'))
                                                ->subDay();
                                            $set('end_date', $endDate->format('Y-m-d'));
                                        }
                                    }),
                                Forms\Components\DatePicker::make('service_start_date')
                                    ->label('施術開始日')
                                    ->required()
                                    ->default(now())
                                    ->helperText('サブスク限定メニューが利用可能になる日'),
                                Forms\Components\TextInput::make('contract_months')
                                    ->label('契約期間')
                                    ->numeric()
                                    ->suffix('ヶ月')
                                    ->default(3)
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        if ($state && $get('billing_start_date')) {
                                            $endDate = \Carbon\Carbon::parse($get('billing_start_date'))
                                                ->addMonths($state)
                                                ->subDay();
                                            $set('end_date', $endDate->format('Y-m-d'));
                                        }
                                    }),
                                Forms\Components\DatePicker::make('end_date')
                                    ->label('契約終了日')
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText('課金開始日と契約期間から自動計算'),
                                Forms\Components\TextInput::make('monthly_price')
                                    ->label('月額料金')
                                    ->numeric()
                                    ->prefix('¥')
                                    ->disabled()
                                    ->dehydrated(),
                                Forms\Components\TextInput::make('monthly_limit')
                                    ->label('月間利用回数')
                                    ->numeric()
                                    ->suffix('回')
                                    ->disabled()
                                    ->dehydrated()
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
                            ->collapsible()
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
                Tables\Columns\IconColumn::make('has_subscription')
                    ->label('サブスク')
                    ->getStateUsing(fn ($record) => $record->hasActiveSubscription())
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
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
                    ->relationship('reservations', 'store_id')
                    ->options(\App\Models\Store::where('is_active', true)->pluck('name', 'id'))
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
        // 暫定対応: 全顧客を表示可能にする（インポート対策）
        return true;
        
        // 以下は将来の実装用（コメントアウト）
        /*
        try {
            $reservationCount = \DB::table('reservations')
                ->where('customer_id', $record->id)
                ->count();
            
            if ($reservationCount === 0) {
                return true; // インポート顧客は表示
            }
            
            $user = auth()->user();
            if (!$user) return false;
            
            // 権限チェックロジック...
            
        } catch (\Exception $e) {
            return true; // エラー時も表示（暫定対応）
        }
        */
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
}