# 🛠️ Filament管理画面設計書

## 概要

Xsyumeno Laravel版の管理画面をFilament 3.xで構築する詳細設計書です。効率的で直感的な管理インターフェースを提供し、管理者の業務効率を最大化します。

## Filament設定

### 基本設定
```php
<?php
// config/filament.php

return [
    'path' => 'admin',
    'domain' => null,
    'home_url' => '/',
    'brand' => 'Xsyumeno Admin',
    'auth' => [
        'guard' => 'web',
        'pages' => [
            'login' => App\Filament\Pages\Auth\Login::class,
        ],
    ],
    'pages' => [
        'namespace' => 'App\\Filament\\Pages',
        'path' => app_path('Filament/Pages'),
    ],
    'resources' => [
        'namespace' => 'App\\Filament\\Resources',
        'path' => app_path('Filament/Resources'),
    ],
    'widgets' => [
        'namespace' => 'App\\Filament\\Widgets',
        'path' => app_path('Filament/Widgets'),
    ],
];
```

### サービスプロバイダー設定
```php
<?php
// app/Providers/FilamentServiceProvider.php

namespace App\Providers;

use Spatie\LaravelServiceProvider;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;

class FilamentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Filament::serving(function () {
            // ダークモード無効化
            Filament::registerTheme(
                app()->environment('local') ? 'http://localhost:5173/css/filament.css' : '/css/filament.css',
            );
            
            // ナビゲーション設定
            Filament::navigation(function () {
                return [
                    NavigationGroup::make('ダッシュボード')
                        ->items([
                            NavigationItem::make('ダッシュボード')
                                ->icon('heroicon-o-home')
                                ->url('/admin'),
                        ]),
                        
                    NavigationGroup::make('顧客管理')
                        ->items([
                            NavigationItem::make('顧客一覧')
                                ->icon('heroicon-o-users')
                                ->url('/admin/customers'),
                            NavigationItem::make('予約管理')
                                ->icon('heroicon-o-calendar')
                                ->url('/admin/reservations'),
                        ]),
                        
                    NavigationGroup::make('店舗管理')
                        ->items([
                            NavigationItem::make('店舗設定')
                                ->icon('heroicon-o-building-office')
                                ->url('/admin/stores'),
                            NavigationItem::make('メニュー管理')
                                ->icon('heroicon-o-list-bullet')
                                ->url('/admin/menus'),
                            NavigationItem::make('スタッフ管理')
                                ->icon('heroicon-o-user-group')
                                ->url('/admin/users'),
                        ]),
                        
                    NavigationGroup::make('業務管理')
                        ->items([
                            NavigationItem::make('シフト管理')
                                ->icon('heroicon-o-clock')
                                ->url('/admin/shifts'),
                            NavigationItem::make('カルテ管理')
                                ->icon('heroicon-o-document-text')
                                ->url('/admin/medical-records'),
                        ]),
                ];
            });
        });
    }
}
```

## リソース設計

### Customer Resource
```php
<?php
// app/Filament/Resources/CustomerResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = '顧客管理';
    protected static ?string $navigationGroup = '顧客管理';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('last_name')
                                    ->label('姓')
                                    ->required()
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('first_name')
                                    ->label('名')
                                    ->required()
                                    ->maxLength(100),
                            ]),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('last_name_kana')
                                    ->label('姓（カナ）')
                                    ->regex('/^[ァ-ヶー]+$/u')
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('first_name_kana')
                                    ->label('名（カナ）')
                                    ->regex('/^[ァ-ヶー]+$/u')
                                    ->maxLength(100),
                            ]),
                    ]),
                    
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
                            ->unique(ignoreRecord: true),
                    ]),
                    
                Forms\Components\Section::make('個人情報')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('birth_date')
                                    ->label('生年月日')
                                    ->displayFormat('Y/m/d')
                                    ->before('today'),
                                Forms\Components\Select::make('gender')
                                    ->label('性別')
                                    ->options([
                                        'male' => '男性',
                                        'female' => '女性',
                                        'other' => 'その他',
                                    ]),
                            ]),
                        Forms\Components\TextInput::make('postal_code')
                            ->label('郵便番号')
                            ->mask('999-9999')
                            ->placeholder('123-4567'),
                        Forms\Components\Textarea::make('address')
                            ->label('住所')
                            ->rows(2),
                    ]),
                    
                Forms\Components\Section::make('設定')
                    ->schema([
                        Forms\Components\KeyValue::make('preferences')
                            ->label('顧客設定')
                            ->keyLabel('項目')
                            ->valueLabel('値'),
                        Forms\Components\Textarea::make('medical_notes')
                            ->label('医療メモ')
                            ->rows(3),
                        Forms\Components\Toggle::make('is_blocked')
                            ->label('ブロック状態'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('氏名')
                    ->searchable(['last_name', 'first_name'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('電話番号')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('メールアドレス')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\BadgeColumn::make('phone_verified_at')
                    ->label('認証状況')
                    ->formatStateUsing(fn ($state) => $state ? '認証済み' : '未認証')
                    ->colors([
                        'success' => fn ($state) => $state,
                        'warning' => fn ($state) => !$state,
                    ]),
                Tables\Columns\TextColumn::make('reservations_count')
                    ->label('予約数')
                    ->counts('reservations')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_visit_at')
                    ->label('最終来店')
                    ->dateTime('Y/m/d')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('is_blocked')
                    ->label('状態')
                    ->formatStateUsing(fn ($state) => $state ? 'ブロック' : 'アクティブ')
                    ->colors([
                        'danger' => fn ($state) => $state,
                        'success' => fn ($state) => !$state,
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('登録日')
                    ->dateTime('Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('phone_verified_at')
                    ->label('認証状況')
                    ->placeholder('すべて')
                    ->trueLabel('認証済み')
                    ->falseLabel('未認証')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('phone_verified_at'),
                        false: fn (Builder $query) => $query->whereNull('phone_verified_at'),
                    ),
                Tables\Filters\TernaryFilter::make('is_blocked')
                    ->label('ブロック状態'),
                Tables\Filters\Filter::make('last_visit_range')
                    ->form([
                        Forms\Components\DatePicker::make('last_visit_from')
                            ->label('最終来店日（開始）'),
                        Forms\Components\DatePicker::make('last_visit_to')
                            ->label('最終来店日（終了）'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['last_visit_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('last_visit_at', '>=', $date),
                            )
                            ->when(
                                $data['last_visit_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('last_visit_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('send_sms')
                    ->label('SMS送信')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->form([
                        Forms\Components\Textarea::make('message')
                            ->label('メッセージ')
                            ->required()
                            ->rows(3)
                            ->maxLength(160),
                    ])
                    ->action(function (Customer $record, array $data): void {
                        // SMS送信処理
                        // $smsService->send($record->phone, $data['message']);
                        
                        Notification::make()
                            ->title('SMS送信完了')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\BulkAction::make('toggle_block')
                    ->label('ブロック状態切り替え')
                    ->icon('heroicon-o-lock-closed')
                    ->action(function (Collection $records): void {
                        $records->each(function (Customer $record) {
                            $record->update(['is_blocked' => !$record->is_blocked]);
                        });
                        
                        Notification::make()
                            ->title('ブロック状態を更新しました')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            CustomerResource\RelationManagers\ReservationsRelationManager::class,
            CustomerResource\RelationManagers\MedicalRecordsRelationManager::class,
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

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['reservations']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['last_name', 'first_name', 'phone', 'email'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            '電話番号' => $record->phone,
            'メール' => $record->email ?? '未設定',
            '最終来店' => $record->last_visit_at?->format('Y/m/d') ?? '未来店',
        ];
    }
}
```

### Reservation Resource
```php
<?php
// app/Filament/Resources/ReservationResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservationResource\Pages;
use App\Models\Reservation;
use App\Models\Store;
use App\Models\Customer;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = '予約管理';
    protected static ?string $navigationGroup = '顧客管理';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('予約基本情報')
                    ->schema([
                        Forms\Components\TextInput::make('reservation_number')
                            ->label('予約番号')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('store_id')
                                    ->label('店舗')
                                    ->options(Store::pluck('name', 'id'))
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(fn (callable $set) => $set('staff_id', null)),
                                Forms\Components\Select::make('customer_id')
                                    ->label('顧客')
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search): array => 
                                        Customer::where('last_name', 'like', "%{$search}%")
                                            ->orWhere('first_name', 'like', "%{$search}%")
                                            ->orWhere('phone', 'like', "%{$search}%")
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn ($customer) => [$customer->id => "{$customer->full_name} ({$customer->phone})"]))
                                    ->required(),
                            ]),
                        Forms\Components\Select::make('staff_id')
                            ->label('担当スタッフ')
                            ->options(function (callable $get) {
                                $storeId = $get('store_id');
                                if (!$storeId) return [];
                                
                                return User::where('store_id', $storeId)
                                    ->where('is_active', true)
                                    ->pluck('name', 'id');
                            })
                            ->reactive(),
                    ]),
                    
                Forms\Components\Section::make('日時設定')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\DatePicker::make('reservation_date')
                                    ->label('予約日')
                                    ->required()
                                    ->minDate(today())
                                    ->displayFormat('Y/m/d'),
                                Forms\Components\TimePicker::make('start_time')
                                    ->label('開始時刻')
                                    ->required()
                                    ->displayFormat('H:i'),
                                Forms\Components\TimePicker::make('end_time')
                                    ->label('終了時刻')
                                    ->required()
                                    ->displayFormat('H:i')
                                    ->after('start_time'),
                            ]),
                    ]),
                    
                Forms\Components\Section::make('予約詳細')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('ステータス')
                                    ->options([
                                        'pending' => 'ペンディング',
                                        'confirmed' => '確定',
                                        'in_progress' => '進行中',
                                        'completed' => '完了',
                                        'cancelled' => 'キャンセル',
                                        'no_show' => '無断欠席',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('guest_count')
                                    ->label('来店人数')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(10)
                                    ->default(1),
                            ]),
                        Forms\Components\Repeater::make('menu_items')
                            ->label('選択メニュー')
                            ->schema([
                                Forms\Components\Select::make('menu_id')
                                    ->label('メニュー')
                                    ->options(function (callable $get) {
                                        $storeId = $get('../../store_id');
                                        if (!$storeId) return [];
                                        
                                        return \App\Models\Menu::where('store_id', $storeId)
                                            ->where('is_available', true)
                                            ->pluck('name', 'id');
                                    })
                                    ->required(),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('数量')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1),
                                Forms\Components\TextInput::make('price')
                                    ->label('価格')
                                    ->numeric()
                                    ->prefix('¥'),
                            ])
                            ->columns(3)
                            ->defaultItems(1),
                    ]),
                    
                Forms\Components\Section::make('金額・支払い')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('total_amount')
                                    ->label('合計金額')
                                    ->numeric()
                                    ->prefix('¥')
                                    ->default(0),
                                Forms\Components\TextInput::make('deposit_amount')
                                    ->label('預かり金')
                                    ->numeric()
                                    ->prefix('¥')
                                    ->default(0),
                                Forms\Components\Select::make('payment_method')
                                    ->label('支払方法')
                                    ->options([
                                        'cash' => '現金',
                                        'card' => 'カード',
                                        'bank_transfer' => '銀行振込',
                                        'paypay' => 'PayPay',
                                    ]),
                            ]),
                        Forms\Components\Select::make('payment_status')
                            ->label('支払状況')
                            ->options([
                                'unpaid' => '未払い',
                                'paid' => '支払済み',
                                'refunded' => '返金済み',
                            ])
                            ->default('unpaid'),
                    ]),
                    
                Forms\Components\Section::make('備考・キャンセル')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('備考')
                            ->rows(3),
                        Forms\Components\Textarea::make('cancel_reason')
                            ->label('キャンセル理由')
                            ->rows(2)
                            ->visible(fn (callable $get) => in_array($get('status'), ['cancelled', 'no_show'])),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reservation_number')
                    ->label('予約番号')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('customer.full_name')
                    ->label('顧客名')
                    ->searchable(['customer.last_name', 'customer.first_name'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('店舗')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reservation_date')
                    ->label('予約日')
                    ->date('Y/m/d')
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('時間')
                    ->formatStateUsing(fn ($record) => $record->start_time . ' - ' . $record->end_time),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('ステータス')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'pending' => 'ペンディング',
                            'confirmed' => '確定',
                            'in_progress' => '進行中',
                            'completed' => '完了',
                            'cancelled' => 'キャンセル',
                            'no_show' => '無断欠席',
                        };
                    })
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'primary' => 'in_progress',
                        'success' => 'completed',
                        'danger' => ['cancelled', 'no_show'],
                    ]),
                Tables\Columns\TextColumn::make('staff.name')
                    ->label('担当スタッフ')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('金額')
                    ->money('JPY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('店舗')
                    ->options(Store::pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('status')
                    ->label('ステータス')
                    ->options([
                        'pending' => 'ペンディング',
                        'confirmed' => '確定',
                        'in_progress' => '進行中',
                        'completed' => '完了',
                        'cancelled' => 'キャンセル',
                        'no_show' => '無断欠席',
                    ]),
                Tables\Filters\Filter::make('reservation_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('開始日'),
                        Forms\Components\DatePicker::make('until')
                            ->label('終了日'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('reservation_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('reservation_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('change_status')
                    ->label('ステータス変更')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('新しいステータス')
                            ->options([
                                'pending' => 'ペンディング',
                                'confirmed' => '確定',
                                'in_progress' => '進行中',
                                'completed' => '完了',
                                'cancelled' => 'キャンセル',
                                'no_show' => '無断欠席',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('備考')
                            ->rows(2),
                    ])
                    ->action(function (Reservation $record, array $data): void {
                        $record->update([
                            'status' => $data['status'],
                            'notes' => $data['notes'] ?? $record->notes,
                        ]);
                        
                        if ($data['status'] === 'confirmed') {
                            $record->update(['confirmed_at' => now()]);
                        } elseif (in_array($data['status'], ['cancelled', 'no_show'])) {
                            $record->update(['cancelled_at' => now()]);
                        }
                        
                        Notification::make()
                            ->title('ステータスを更新しました')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('reservation_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReservations::route('/'),
            'create' => Pages\CreateReservation::route('/create'),
            'view' => Pages\ViewReservation::route('/{record}'),
            'edit' => Pages\EditReservation::route('/{record}/edit'),
        ];
    }
}
```

## ダッシュボードウィジェット

### 統計ウィジェット
```php
<?php
// app/Filament/Widgets/StatsOverviewWidget.php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Store;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class StatsOverviewWidget extends BaseWidget
{
    protected function getCards(): array
    {
        return [
            Card::make('本日の予約', Reservation::whereDate('reservation_date', today())->count())
                ->description('前日比')
                ->descriptionIcon('heroicon-s-trending-up')
                ->color('success'),
                
            Card::make('アクティブ顧客', Customer::whereNotNull('phone_verified_at')->count())
                ->description('認証済み顧客数')
                ->descriptionIcon('heroicon-s-users')
                ->color('primary'),
                
            Card::make('店舗数', Store::where('is_active', true)->count())
                ->description('アクティブ店舗')
                ->descriptionIcon('heroicon-s-building-office')
                ->color('warning'),
                
            Card::make('今月の売上', '¥' . number_format(
                Reservation::whereMonth('reservation_date', now()->month)
                    ->whereYear('reservation_date', now()->year)
                    ->where('status', 'completed')
                    ->sum('total_amount')
            ))
                ->description('今月の完了予約')
                ->descriptionIcon('heroicon-s-currency-yen')
                ->color('success'),
        ];
    }
}
```

### 予約チャートウィジェット
```php
<?php
// app/Filament/Widgets/ReservationChartWidget.php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use Filament\Widgets\LineChartWidget;
use Carbon\Carbon;

class ReservationChartWidget extends LineChartWidget
{
    protected static ?string $heading = '週間予約推移';
    
    protected function getData(): array
    {
        $data = [];
        $labels = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $labels[] = $date->format('n/j');
            $data[] = Reservation::whereDate('reservation_date', $date)->count();
        }
        
        return [
            'datasets' => [
                [
                    'label' => '予約数',
                    'data' => $data,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                ],
            ],
            'labels' => $labels,
        ];
    }
}
```

### 最近のアクティビティウィジェット
```php
<?php
// app/Filament/Widgets/LatestActivitiesWidget.php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use App\Models\Customer;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestActivitiesWidget extends BaseWidget
{
    protected static ?string $heading = '最近のアクティビティ';
    protected int | string | array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return Reservation::query()
            ->with(['customer', 'store'])
            ->latest()
            ->limit(10);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('created_at')
                ->label('日時')
                ->dateTime('m/d H:i')
                ->sortable(),
            Tables\Columns\TextColumn::make('customer.full_name')
                ->label('顧客名'),
            Tables\Columns\TextColumn::make('store.name')
                ->label('店舗'),
            Tables\Columns\BadgeColumn::make('status')
                ->label('ステータス')
                ->formatStateUsing(function ($state) {
                    return match ($state) {
                        'pending' => '新規予約',
                        'confirmed' => '予約確定',
                        'cancelled' => 'キャンセル',
                        default => $state,
                    };
                })
                ->colors([
                    'primary' => 'pending',
                    'success' => 'confirmed',
                    'danger' => 'cancelled',
                ]),
            Tables\Columns\TextColumn::make('reservation_date')
                ->label('予約日')
                ->date('m/d'),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('view')
                ->label('詳細')
                ->url(fn (Reservation $record): string => route('filament.resources.reservations.view', $record))
                ->openUrlInNewTab(),
        ];
    }
}
```

## カスタムページ

### カレンダーページ
```php
<?php
// app/Filament/Pages/ReservationCalendar.php

namespace App\Filament\Pages;

use App\Models\Reservation;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;

class ReservationCalendar extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static string $view = 'filament.pages.reservation-calendar';
    protected static ?string $navigationLabel = '予約カレンダー';
    protected static ?string $navigationGroup = '顧客管理';
    protected static ?int $navigationSort = 3;

    public function getViewData(): array
    {
        $reservations = Reservation::with(['customer', 'store', 'staff'])
            ->whereDate('reservation_date', '>=', now()->startOfMonth())
            ->whereDate('reservation_date', '<=', now()->endOfMonth())
            ->get()
            ->map(function ($reservation) {
                return [
                    'id' => $reservation->id,
                    'title' => $reservation->customer->full_name,
                    'start' => $reservation->reservation_date->format('Y-m-d') . 'T' . $reservation->start_time,
                    'end' => $reservation->reservation_date->format('Y-m-d') . 'T' . $reservation->end_time,
                    'backgroundColor' => $this->getStatusColor($reservation->status),
                    'borderColor' => $this->getStatusColor($reservation->status),
                ];
            });

        return [
            'reservations' => $reservations,
        ];
    }

    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => '#f59e0b',
            'confirmed' => '#10b981',
            'in_progress' => '#3b82f6',
            'completed' => '#6b7280',
            'cancelled' => '#ef4444',
            'no_show' => '#dc2626',
            default => '#6b7280',
        };
    }
}
```

## 権限管理

### Policy設定
```php
<?php
// app/Policies/CustomerPolicy.php

namespace App\Policies;

use App\Models\User;
use App\Models\Customer;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view') && $this->canAccessStore($user, $customer);
    }

    public function create(User $user): bool
    {
        return $user->can('customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.edit') && $this->canAccessStore($user, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('customers.delete') && $this->canAccessStore($user, $customer);
    }

    private function canAccessStore(User $user, Customer $customer): bool
    {
        // スーパー管理者は全てアクセス可能
        if ($user->hasRole('superadmin')) {
            return true;
        }

        // 管理者は全店舗アクセス可能
        if ($user->hasRole('admin')) {
            return true;
        }

        // 店舗スタッフは自分の店舗のみ
        return $customer->reservations()
            ->whereHas('store', fn ($q) => $q->where('id', $user->store_id))
            ->exists();
    }
}
```

このFilament管理画面設計により、効率的で使いやすい管理インターフェースを提供できます。