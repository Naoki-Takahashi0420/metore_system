<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MedicalRecordResource\Pages;
use App\Filament\Resources\MedicalRecordResource\RelationManagers;
use App\Models\MedicalRecord;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MedicalRecordResource extends Resource
{
    protected static ?string $model = MedicalRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationLabel = 'カルテ管理';
    
    protected static ?string $modelLabel = 'カルテ';
    
    protected static ?string $pluralModelLabel = 'カルテ';
    
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('カルテ情報')
                    ->tabs([
                        // 基本情報タブ
                        Forms\Components\Tabs\Tab::make('基本情報')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('customer_id')
                                            ->label('顧客')
                                            ->searchable()
                                            ->getSearchResultsUsing(function (string $search) {
                                                $user = auth()->user();
                                                $query = Customer::query();

                                                // 検索条件
                                                $query->where(function ($q) use ($search) {
                                                    $q->where('last_name', 'like', "%{$search}%")
                                                      ->orWhere('first_name', 'like', "%{$search}%")
                                                      ->orWhere('phone', 'like', "%{$search}%")
                                                      ->orWhere('last_name_kana', 'like', "%{$search}%")
                                                      ->orWhere('first_name_kana', 'like', "%{$search}%");
                                                });

                                                // 権限による絞り込み
                                                if (!$user->hasRole('super_admin')) {
                                                    if ($user->hasRole('owner')) {
                                                        $storeIds = $user->manageableStores()->pluck('stores.id')->toArray();
                                                        // 管理店舗に所属する顧客 OR 管理店舗で予約履歴がある顧客
                                                        $query->where(function ($q) use ($storeIds) {
                                                            $q->whereIn('store_id', $storeIds)
                                                              ->orWhereHas('reservations', function ($subQ) use ($storeIds) {
                                                                  $subQ->whereIn('store_id', $storeIds);
                                                              });
                                                        });
                                                    } elseif ($user->store_id) {
                                                        // 自店舗に所属する顧客 OR 自店舗で予約履歴がある顧客
                                                        $query->where(function ($q) use ($user) {
                                                            $q->where('store_id', $user->store_id)
                                                              ->orWhereHas('reservations', function ($subQ) use ($user) {
                                                                  $subQ->where('store_id', $user->store_id);
                                                              });
                                                        });
                                                    } else {
                                                        return [];
                                                    }
                                                }

                                                return $query->limit(50)->get()->mapWithKeys(function ($customer) {
                                                    $label = ($customer->last_name ?? '') . ' ' . ($customer->first_name ?? '') . ' (' . ($customer->phone ?? '') . ')';
                                                    return [$customer->id => $label];
                                                });
                                            })
                                            ->getOptionLabelUsing(function ($value) {
                                                $customer = Customer::find($value);
                                                if (!$customer) return $value;
                                                return ($customer->last_name ?? '') . ' ' . ($customer->first_name ?? '') . ' (' . ($customer->phone ?? '') . ')';
                                            })
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(fn ($state, callable $set) => $set('customer_characteristics',
                                                $state ? Customer::find($state)?->characteristics : null
                                            )),
                                        
                                        Forms\Components\Select::make('reservation_id')
                                            ->label('予約（任意）')
                                            ->helperText('予約に紐づかないカルテ（引き継ぎメモなど）の場合は、ここに何も入力しないでください')
                                            ->options(function ($get) {
                                                if (!$get('customer_id')) {
                                                    return [];
                                                }
                                                return Reservation::with(['store', 'menu'])
                                                    ->where('customer_id', $get('customer_id'))
                                                    ->orderBy('reservation_date', 'desc')
                                                    ->get()
                                                    ->mapWithKeys(function ($reservation) {
                                                        $date = $reservation->reservation_date ? $reservation->reservation_date->format('Y/m/d') : '';
                                                        $time = $reservation->start_time ? ' ' . date('H:i', strtotime($reservation->start_time)) : '';
                                                        $menu = $reservation->menu ? ' - ' . $reservation->menu->name : '';
                                                        $store = $reservation->store ? ' (' . $reservation->store->name . ')' : '';
                                                        $status = $reservation->status === 'completed' ? ' [完了]' : '';

                                                        $label = $date . $time . $menu . $store . $status;
                                                        return [$reservation->id => $label];
                                                    });
                                            })
                                            ->searchable()
                                            ->reactive()
                                            ->nullable()
                                            ->afterStateUpdated(function ($state, $set) {
                                                // 予約選択時に担当スタッフを自動設定
                                                if ($state) {
                                                    $reservation = Reservation::with(['staff'])->find($state);
                                                    if ($reservation && $reservation->staff) {
                                                        $set('handled_by', $reservation->staff->name);
                                                    }
                                                }
                                            }),
                                        
                                        Forms\Components\Select::make('handled_by')
                                            ->label('対応者')
                                            ->options(function ($get) {
                                                $user = Auth::user();

                                                // 予約から店舗情報を取得
                                                $reservationId = $get('reservation_id');
                                                if ($reservationId) {
                                                    $reservation = Reservation::with(['store'])->find($reservationId);
                                                    if ($reservation && $reservation->store_id) {
                                                        // 店舗に紐づくスタッフを取得
                                                        return \App\Models\User::where('store_id', $reservation->store_id)
                                                            ->where('is_active', true)
                                                            ->pluck('name', 'name');
                                                    }
                                                }

                                                // 予約が未選択の場合は、ユーザーの権限に応じて制限
                                                $query = \App\Models\User::where('is_active', true);

                                                // スーパーアドミンは全スタッフ表示
                                                if ($user && $user->hasRole('super_admin')) {
                                                    return $query->pluck('name', 'name');
                                                }

                                                // オーナーは管理可能店舗のスタッフのみ
                                                if ($user && $user->hasRole('owner')) {
                                                    $manageableStoreIds = $user->manageableStores()->pluck('stores.id');
                                                    return $query->whereIn('store_id', $manageableStoreIds)->pluck('name', 'name');
                                                }

                                                // 店長・スタッフは所属店舗のスタッフのみ
                                                if ($user && $user->store_id) {
                                                    return $query->where('store_id', $user->store_id)->pluck('name', 'name');
                                                }

                                                // デフォルトは全スタッフ（念のため）
                                                return $query->pluck('name', 'name');
                                            })
                                            ->default(Auth::user()->name)
                                            ->searchable()
                                            ->reactive()
                                            ->placeholder('対応者を選択（任意）'),
                                        
                                        Forms\Components\DatePicker::make('treatment_date')
                                            ->label('施術日')
                                            ->default(now())
                                            ->required(),

                                        Forms\Components\TextInput::make('age')
                                            ->label('年齢')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(150)
                                            ->suffix('歳')
                                            ->placeholder('年齢を入力（任意）'),
                                    ]),

                                // 顧客特性表示（スタッフ用情報）
                                Forms\Components\Placeholder::make('customer_characteristics_display')
                                    ->label('')
                                    ->content(function ($get) {
                                        $customerId = $get('customer_id');
                                        if (!$customerId) {
                                            return '';
                                        }

                                        $customer = Customer::find($customerId);
                                        if (!$customer || !$customer->characteristics) {
                                            return '';
                                        }

                                        return new \Illuminate\Support\HtmlString(
                                            '<div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">' .
                                            '<div class="flex items-start gap-2">' .
                                            '<svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' .
                                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>' .
                                            '</svg>' .
                                            '<div class="flex-1">' .
                                            '<h4 class="font-semibold text-yellow-800 dark:text-yellow-200 mb-1">顧客特性（スタッフ用メモ）</h4>' .
                                            '<p class="text-sm text-yellow-700 dark:text-yellow-300 whitespace-pre-wrap">' . nl2br(e($customer->characteristics)) . '</p>' .
                                            '</div>' .
                                            '</div>' .
                                            '</div>'
                                        );
                                    })
                                    ->columnSpanFull()
                                    ->visible(fn ($get) => (bool) $get('customer_id')),
                            ]),
                        
                        // 顧客管理情報タブ（常に表示）
                        Forms\Components\Tabs\Tab::make('顧客管理情報')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('payment_method')
                                            ->label('支払い方法')
                                            ->options(function ($get) {
                                                $store = null;

                                                // 1. 予約から店舗情報を取得
                                                $reservationId = $get('reservation_id');
                                                if ($reservationId) {
                                                    $reservation = Reservation::with(['store'])->find($reservationId);
                                                    if ($reservation && $reservation->store) {
                                                        $store = $reservation->store;
                                                    }
                                                }

                                                // 2. 予約がない場合は、顧客から店舗を取得
                                                if (!$store) {
                                                    $customerId = $get('customer_id');
                                                    if ($customerId) {
                                                        $customer = \App\Models\Customer::with('store')->find($customerId);
                                                        if ($customer && $customer->store) {
                                                            $store = $customer->store;
                                                        }
                                                    }
                                                }

                                                // 3. それでもない場合は、ログインユーザーの店舗を取得
                                                if (!$store) {
                                                    $user = Auth::user();
                                                    if ($user && $user->store) {
                                                        $store = $user->store;
                                                    }
                                                }

                                                // 店舗の支払い方法設定を使用
                                                if ($store && $store->payment_methods) {
                                                    $options = [];
                                                    foreach ($store->payment_methods as $method) {
                                                        // 新しいシンプル構造: ['name' => '現金']
                                                        if (is_array($method) && isset($method['name'])) {
                                                            $options[$method['name']] = $method['name'];
                                                        }
                                                        // 旧キー・ラベル構造: ['key' => 'cash', 'label' => '現金']
                                                        elseif (is_array($method) && isset($method['key']) && isset($method['label'])) {
                                                            $options[$method['key']] = $method['label'];
                                                        }
                                                        // 古い構造: 'cash'
                                                        elseif (is_string($method)) {
                                                            $legacyLabels = [
                                                                'cash' => '現金',
                                                                'credit' => 'クレジットカード',
                                                                'paypay' => 'PayPay',
                                                                'bank_transfer' => '銀行振込',
                                                                'subscription' => 'サブスク',
                                                            ];
                                                            $options[$method] = $legacyLabels[$method] ?? $method;
                                                        }
                                                    }
                                                    return $options;
                                                }

                                                // デフォルト（店舗が取得できない場合のみ）
                                                return [
                                                    'cash' => '現金',
                                                    'credit' => 'クレジットカード',
                                                    'paypay' => 'PayPay',
                                                    'bank_transfer' => '銀行振込',
                                                    'subscription' => 'サブスク',
                                                ];
                                            })
                                            ->reactive(),
                                        
                                        Forms\Components\Select::make('reservation_source')
                                            ->label('来店経路')
                                            ->options(function ($get) {
                                                // 予約から店舗情報を取得
                                                $reservationId = $get('reservation_id');
                                                if ($reservationId) {
                                                    $reservation = Reservation::with(['store'])->find($reservationId);
                                                    if ($reservation && $reservation->store && $reservation->store->visit_sources) {
                                                        $options = [];
                                                        foreach ($reservation->store->visit_sources as $source) {
                                                            // 新しいシンプル構造: ['name' => 'ホームページ']
                                                            if (is_array($source) && isset($source['name'])) {
                                                                $options[$source['name']] = $source['name'];
                                                            }
                                                            // 旧キー・ラベル構造: ['key' => 'hp', 'label' => 'ホームページ']
                                                            elseif (is_array($source) && isset($source['key']) && isset($source['label'])) {
                                                                $options[$source['key']] = $source['label'];
                                                            }
                                                            // 古い構造: 'hp'
                                                            elseif (is_string($source)) {
                                                                $legacyLabels = [
                                                                    'hp' => 'ホームページ',
                                                                    'phone' => '電話',
                                                                    'line' => 'LINE',
                                                                    'instagram' => 'Instagram',
                                                                    'referral' => '紹介',
                                                                    'walk_in' => '飛び込み',
                                                                ];
                                                                $options[$source] = $legacyLabels[$source] ?? $source;
                                                            }
                                                        }
                                                        return $options;
                                                    }
                                                }
                                                
                                                // デフォルト（店舗未選択時）
                                                return [
                                                    'hp' => 'ホームページ',
                                                    'phone' => '電話',
                                                    'line' => 'LINE',
                                                    'instagram' => 'Instagram',
                                                    'referral' => '紹介',
                                                    'walk_in' => '飛び込み',
                                                ];
                                            })
                                            ->reactive(),
                                        
                                        Forms\Components\Textarea::make('visit_purpose')
                                            ->label('来店目的')
                                            ->rows(2),
                                        
                                        Forms\Components\Textarea::make('workplace_address')
                                            ->label('職場・住所')
                                            ->rows(2),
                                    ]),
                                
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Toggle::make('genetic_possibility')
                                            ->label('遺伝の可能性'),
                                        
                                        Forms\Components\Toggle::make('has_astigmatism')
                                            ->label('乱視'),
                                        
                                        Forms\Components\Textarea::make('eye_diseases')
                                            ->label('目の病気')
                                            ->placeholder('レーシック、白内障など')
                                            ->rows(2)
                                            ->columnSpan(3),
                                    ]),
                                
                                Forms\Components\Textarea::make('device_usage')
                                    ->label('スマホ・PC使用頻度')
                                    ->placeholder('1日何時間程度、仕事で使用など')
                                    ->rows(2),
                            ]),
                        
                        // 視力記録タブ（顧客に見せる）
                        Forms\Components\Tabs\Tab::make('視力記録')
                            ->schema([
                                Forms\Components\Repeater::make('vision_records')
                                    ->label('視力測定記録')
                                    ->schema([
                                        Forms\Components\Grid::make(4)
                                            ->schema([
                                                Forms\Components\Hidden::make('session')
                                                    ->default(1),

                                                Forms\Components\TextInput::make('display_session')
                                                    ->label('回数')
                                                    ->default('自動設定')
                                                    ->disabled()
                                                    ->dehydrated(false),

                                                Forms\Components\DatePicker::make('date')
                                                    ->label('測定日')
                                                    ->default(now())
                                                    ->required(),

                                                Forms\Components\TextInput::make('intensity')
                                                    ->label('強度')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->maxValue(50)
                                                    ->placeholder('1-50')
                                                    ->helperText('1（弱）〜 50（強）'),

                                                Forms\Components\Select::make('duration')
                                                    ->label('時間（分）')
                                                    ->options([
                                                        30 => '30分',
                                                        50 => '50分',
                                                        80 => '80分'
                                                    ])
                                                    ->default(function ($get) {
                                                        // 予約からメニュー情報を取得して時間を自動設定
                                                        $reservationId = $get('../../reservation_id');
                                                        if ($reservationId) {
                                                            $reservation = Reservation::with(['menu'])->find($reservationId);
                                                            if ($reservation && $reservation->menu && $reservation->menu->duration_minutes) {
                                                                $duration = $reservation->menu->duration_minutes;
                                                                // 最も近い選択肢を選ぶ
                                                                if ($duration <= 30) return 30;
                                                                if ($duration <= 50) return 50;
                                                                return 80;
                                                            }
                                                        }
                                                        return 50; // デフォルト50分
                                                    })
                                                    ->required(),
                                            ]),

                                        // 施術前視力（1回目：裸眼）
                                        Forms\Components\Section::make('施術前視力 - 裸眼')
                                            ->schema([
                                                Forms\Components\Grid::make(2)
                                                    ->schema([
                                                        Forms\Components\TextInput::make('before_naked_left')
                                                            ->label('左眼')
                                                            ->placeholder('0.5'),

                                                        Forms\Components\TextInput::make('before_naked_right')
                                                            ->label('右眼')
                                                            ->placeholder('0.5'),
                                                    ]),
                                            ])
                                            ->collapsible(),

                                        // 施術前視力（2回目：矯正）
                                        Forms\Components\Section::make('施術前視力 - 矯正（メガネ・コンタクト）')
                                            ->schema([
                                                Forms\Components\Grid::make(2)
                                                    ->schema([
                                                        Forms\Components\TextInput::make('before_corrected_left')
                                                            ->label('左眼')
                                                            ->placeholder('1.0'),

                                                        Forms\Components\TextInput::make('before_corrected_right')
                                                            ->label('右眼')
                                                            ->placeholder('1.0'),
                                                    ]),
                                            ])
                                            ->collapsible()
                                            ->collapsed(),

                                        // 施術後視力（1回目：裸眼）
                                        Forms\Components\Section::make('施術後視力 - 裸眼')
                                            ->schema([
                                                Forms\Components\Grid::make(2)
                                                    ->schema([
                                                        Forms\Components\TextInput::make('after_naked_left')
                                                            ->label('左眼')
                                                            ->placeholder('0.8'),

                                                        Forms\Components\TextInput::make('after_naked_right')
                                                            ->label('右眼')
                                                            ->placeholder('0.8'),
                                                    ]),
                                            ])
                                            ->collapsible(),

                                        // 施術後視力（2回目：矯正）
                                        Forms\Components\Section::make('施術後視力 - 矯正（メガネ・コンタクト）')
                                            ->schema([
                                                Forms\Components\Grid::make(2)
                                                    ->schema([
                                                        Forms\Components\TextInput::make('after_corrected_left')
                                                            ->label('左眼')
                                                            ->placeholder('1.2'),

                                                        Forms\Components\TextInput::make('after_corrected_right')
                                                            ->label('右眼')
                                                            ->placeholder('1.2'),
                                                    ]),
                                            ])
                                            ->collapsible()
                                            ->collapsed(),

                                        Forms\Components\Textarea::make('public_memo')
                                            ->label('メモ（顧客に表示）')
                                            ->placeholder('効果の実感など')
                                            ->rows(2),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('視力記録を追加')
                                    ->collapsible()
                                    ->cloneable(),

                                // 老眼詳細測定表（オプション）
                                Forms\Components\Section::make('老眼詳細測定')
                                    ->description('老眼の詳細な測定結果を記録します（任意）')
                                    ->schema([
                                        Forms\Components\Grid::make(11)
                                            ->schema([
                                                // ヘッダー行
                                                Forms\Components\Placeholder::make('header_empty')
                                                    ->label('')
                                                    ->content(''),

                                                Forms\Components\Placeholder::make('header_a')
                                                    ->label('')
                                                    ->content(new \Illuminate\Support\HtmlString('<div class="text-center font-bold">A(95%)</div>'))
                                                    ->columnSpan(2),

                                                Forms\Components\Placeholder::make('header_b')
                                                    ->label('')
                                                    ->content(new \Illuminate\Support\HtmlString('<div class="text-center font-bold">B(50%)</div>'))
                                                    ->columnSpan(2),

                                                Forms\Components\Placeholder::make('header_c')
                                                    ->label('')
                                                    ->content(new \Illuminate\Support\HtmlString('<div class="text-center font-bold">C(25%)</div>'))
                                                    ->columnSpan(2),

                                                Forms\Components\Placeholder::make('header_d')
                                                    ->label('')
                                                    ->content(new \Illuminate\Support\HtmlString('<div class="text-center font-bold">D(12%)</div>'))
                                                    ->columnSpan(2),

                                                Forms\Components\Placeholder::make('header_e')
                                                    ->label('')
                                                    ->content(new \Illuminate\Support\HtmlString('<div class="text-center font-bold">E(6%)</div>'))
                                                    ->columnSpan(2),

                                                // 施術前の行
                                                Forms\Components\Placeholder::make('before_label')
                                                    ->label('')
                                                    ->content(new \Illuminate\Support\HtmlString('<div class="font-bold">施術前</div>')),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.before.a_95_left')
                                                    ->label('左')
                                                    ->placeholder('0.1'),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.before.a_95_right')
                                                    ->label('右')
                                                    ->placeholder('0.1'),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.before.b_50_left')
                                                    ->label('左')
                                                    ->placeholder('0.1'),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.before.b_50_right')
                                                    ->label('右')
                                                    ->placeholder('0.1'),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.before.c_25_left')
                                                    ->label('左')
                                                    ->placeholder('0.1'),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.before.c_25_right')
                                                    ->label('右')
                                                    ->placeholder('0.1'),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.before.d_12_left')
                                                    ->label('左')
                                                    ->placeholder('0.1'),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.before.d_12_right')
                                                    ->label('右')
                                                    ->placeholder('0.1'),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.before.e_6_left')
                                                    ->label('左')
                                                    ->placeholder('0.1'),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.before.e_6_right')
                                                    ->label('右')
                                                    ->placeholder('0.1'),

                                                // 施術後の行
                                                Forms\Components\Placeholder::make('after_label')
                                                    ->label('')
                                                    ->content(new \Illuminate\Support\HtmlString('<div class="font-bold">施術後</div>')),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.after.a_95_left')
                                                    ->label('左')
                                                    ->placeholder('0.1'),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.after.a_95_right')
                                                    ->label('右')
                                                    ->placeholder('0.1'),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.after.b_50_left')
                                                    ->label('左')
                                                    ->placeholder('0.1'),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.after.b_50_right')
                                                    ->label('右')
                                                    ->placeholder('0.1'),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.after.c_25_left')
                                                    ->label('左')
                                                    ->placeholder('0.1'),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.after.c_25_right')
                                                    ->label('右')
                                                    ->placeholder('0.1'),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.after.d_12_left')
                                                    ->label('左')
                                                    ->placeholder('0.1'),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.after.d_12_right')
                                                    ->label('右')
                                                    ->placeholder('0.1'),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.after.e_6_left')
                                                    ->label('左')
                                                    ->placeholder('0.1'),

                                                Forms\Components\TextInput::make('presbyopiaMeasurements.after.e_6_right')
                                                    ->label('右')
                                                    ->placeholder('0.1'),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->collapsed(),
                            ]),
                        
                        // 接客メモタブ（内部用）
                        Forms\Components\Tabs\Tab::make('接客メモ・引き継ぎ')
                            ->schema([
                                Forms\Components\Textarea::make('service_memo')
                                    ->label('接客メモ（内部用・顧客には非表示）')
                                    ->rows(4)
                                    ->placeholder('顧客の様子、対応時の注意点など'),
                                
                                Forms\Components\Textarea::make('next_visit_notes')
                                    ->label('次回引き継ぎ事項')
                                    ->rows(4)
                                    ->placeholder('次回予約時に確認すべきこと、注意点など'),
                                
                                Forms\Components\Textarea::make('notes')
                                    ->label('その他メモ')
                                    ->rows(3),
                            ]),
                        
                        // 予約履歴タブ（修復版 - 無限読み込み問題を解決）
                        Forms\Components\Tabs\Tab::make('予約履歴')
                            ->schema([
                                Forms\Components\Section::make('顧客の予約履歴')
                                    ->description(fn ($record) =>
                                        $record && $record->customer_id
                                            ? '最新10件の予約履歴を表示'
                                            : '顧客を選択してください'
                                    )
                                    ->schema([
                                        // ViewFieldを使用して静的レンダリングに変更
                                        Forms\Components\ViewField::make('reservation_history_view')
                                            ->label('')
                                            ->view('filament.forms.components.reservation-history')
                                            ->viewData(function ($record) {
                                                // recordがない、またはcustomer_idがない場合は空配列を返す
                                                if (!$record || !$record->customer_id) {
                                                    return ['reservations' => collect()];
                                                }

                                                // キャッシュキーを作成（同じ顧客の重複読み込みを防ぐ）
                                                $cacheKey = 'medical_record_reservations_' . $record->customer_id;

                                                // 5秒間のキャッシュで重複読み込みを防ぐ
                                                $reservations = cache()->remember($cacheKey, 5, function () use ($record) {
                                                    return \App\Models\Reservation::where('customer_id', $record->customer_id)
                                                        ->select([
                                                            'id', 'reservation_date', 'start_time', 'end_time',
                                                            'status', 'total_amount', 'store_id', 'menu_id', 'staff_id'
                                                        ])
                                                        ->with([
                                                            'store:id,name',
                                                            'menu:id,name',
                                                            'staff:id,name'
                                                        ])
                                                        ->orderBy('reservation_date', 'desc')
                                                        ->orderBy('start_time', 'desc')
                                                        ->limit(10)
                                                        ->get();
                                                });

                                                return ['reservations' => $reservations];
                                            })
                                            ->columnSpanFull()
                                            // 顧客が選択されている場合のみ表示
                                            ->visible(fn ($record) => $record && $record->customer_id),

                                        // 顧客未選択時のメッセージ
                                        Forms\Components\Placeholder::make('no_customer_message')
                                            ->label('')
                                            ->content('顧客を選択すると予約履歴が表示されます。')
                                            ->visible(fn ($record) => !$record || !$record->customer_id),
                                    ])
                                    // 遅延読み込みで初期ロードを軽減
                                    ->collapsed(fn ($record) => !$record || !$record->customer_id)
                                    ->collapsible(),
                            ])
                    ]) // タブ終了
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store_name')
                    ->label('店舗')
                    ->getStateUsing(function ($record) {
                        // 予約がある場合は予約の店舗、ない場合は顧客の店舗
                        if ($record->reservation && $record->reservation->store) {
                            return $record->reservation->store->name;
                        } elseif ($record->customer && $record->customer->store) {
                            return $record->customer->store->name;
                        }
                        return '-';
                    })
                    ->sortable(query: function ($query, string $direction) {
                        // 予約の店舗 > 顧客の店舗の優先順でソート
                        return $query->leftJoin('reservations', 'medical_records.reservation_id', '=', 'reservations.id')
                            ->leftJoin('stores as reservation_stores', 'reservations.store_id', '=', 'reservation_stores.id')
                            ->leftJoin('customers', 'medical_records.customer_id', '=', 'customers.id')
                            ->leftJoin('stores as customer_stores', 'customers.store_id', '=', 'customer_stores.id')
                            ->orderByRaw("COALESCE(reservation_stores.name, customer_stores.name) {$direction}");
                    })
                    ->searchable(query: function ($query, $search) {
                        return $query->where(function ($q) use ($search) {
                            // 予約の店舗で検索
                            $q->whereHas('reservation', function ($subQ) use ($search) {
                                $subQ->whereHas('store', function ($storeQ) use ($search) {
                                    $storeQ->where('name', 'like', "%{$search}%");
                                });
                            })
                            // または顧客の店舗で検索
                            ->orWhereHas('customer', function ($subQ) use ($search) {
                                $subQ->whereHas('store', function ($storeQ) use ($search) {
                                    $storeQ->where('name', 'like', "%{$search}%");
                                });
                            });
                        });
                    }),
                Tables\Columns\TextColumn::make('customer.last_name')
                    ->label('顧客名')
                    ->formatStateUsing(fn ($record) => $record->customer ? (($record->customer->last_name ?? '') . ' ' . ($record->customer->first_name ?? '')) : '-')
                    ->searchable(query: function ($query, $search) {
                        $dbDriver = \DB::connection()->getDriverName();
                        $search = trim($search);

                        return $query->whereHas('customer', function ($q) use ($search, $dbDriver) {
                            $q->where(function ($subQ) use ($search, $dbDriver) {
                                // 姓・名それぞれで検索
                                $subQ->where('last_name', 'like', "%{$search}%")
                                     ->orWhere('first_name', 'like', "%{$search}%")
                                     ->orWhere('last_name_kana', 'like', "%{$search}%")
                                     ->orWhere('first_name_kana', 'like', "%{$search}%")
                                     ->orWhere('phone', 'like', "%{$search}%")
                                     ->orWhere('email', 'like', "%{$search}%");

                                // フルネーム検索（DB種別で分岐）
                                if ($dbDriver === 'mysql') {
                                    $subQ->orWhereRaw('CONCAT(last_name, first_name) LIKE ?', ["%{$search}%"])
                                         ->orWhereRaw('CONCAT(last_name, " ", first_name) LIKE ?', ["%{$search}%"])
                                         ->orWhereRaw('CONCAT(last_name_kana, first_name_kana) LIKE ?', ["%{$search}%"])
                                         ->orWhereRaw('CONCAT(last_name_kana, " ", first_name_kana) LIKE ?', ["%{$search}%"]);
                                } else {
                                    // SQLiteの場合は || 演算子
                                    $subQ->orWhereRaw('(last_name || first_name) LIKE ?', ["%{$search}%"])
                                         ->orWhereRaw('(last_name || " " || first_name) LIKE ?', ["%{$search}%"])
                                         ->orWhereRaw('(last_name_kana || first_name_kana) LIKE ?', ["%{$search}%"])
                                         ->orWhereRaw('(last_name_kana || " " || first_name_kana) LIKE ?', ["%{$search}%"]);
                                }
                            });
                        });
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('treatment_date')
                    ->label('施術日')
                    ->date('Y/m/d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('age')
                    ->label('年齢')
                    ->suffix('歳')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('session_number')
                    ->label('回数')
                    ->badge()
                    ->color('primary'),
                
                Tables\Columns\TextColumn::make('handled_by')
                    ->label('対応者')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('latest_vision')
                    ->label('最新視力')
                    ->getStateUsing(function (MedicalRecord $record) {
                        $latest = $record->getLatestVisionRecord();
                        if (!$latest) return '-';
                        
                        // 裸眼視力の変化を表示
                        $leftChange = sprintf(
                            'L: %s→%s',
                            $latest['before_naked_left'] ?? '-',
                            $latest['after_naked_left'] ?? '-'
                        );
                        $rightChange = sprintf(
                            'R: %s→%s',
                            $latest['before_naked_right'] ?? '-',
                            $latest['after_naked_right'] ?? '-'
                        );
                        
                        return $leftChange . ' / ' . $rightChange;
                    })
                    ->wrap(),
                
                Tables\Columns\IconColumn::make('has_next_notes')
                    ->label('引継')
                    ->getStateUsing(fn ($record) => !empty($record->next_visit_notes))
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('gray'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日時')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('店舗')
                    ->relationship('reservation.store', 'name')
                    ->searchable(),
                    
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('顧客')
                    ->options(Customer::all()->mapWithKeys(function ($customer) {
                        return [$customer->id => $customer->last_name . ' ' . $customer->first_name];
                    }))
                    ->searchable(),
                
                Tables\Filters\Filter::make('has_next_notes')
                    ->label('引き継ぎありのみ')
                    ->query(fn ($query) => $query->whereNotNull('next_visit_notes')->where('next_visit_notes', '!=', '')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('print')
                    ->label('印刷')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn ($record) => route('medical-record.print', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('treatment_date', 'desc');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        // スーパーアドミンは全カルテにアクセス可能
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // オーナーは管理店舗に関連するカルテのみ表示
        // 予約を通じて店舗と関連がある顧客のカルテを表示
        if ($user->hasRole('owner')) {
            $storeIds = $user->manageableStores()->pluck('stores.id')->toArray();
            return $query->whereHas('customer', function ($q) use ($storeIds) {
                $q->whereHas('reservations', function ($subQ) use ($storeIds) {
                    $subQ->whereIn('store_id', $storeIds);
                });
            });
        }

        // 店長・スタッフは自店舗に関連するカルテのみ表示
        // 予約を通じて店舗と関連がある顧客のカルテを表示
        if ($user->hasRole(['manager', 'staff']) && $user->store_id) {
            return $query->whereHas('customer', function ($q) use ($user) {
                $q->whereHas('reservations', function ($subQ) use ($user) {
                    $subQ->where('store_id', $user->store_id);
                });
            });
        }

        // 該当ロールがない場合は空の結果
        return $query->whereRaw('1 = 0');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ImagesRelationManager::class,
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMedicalRecords::route('/'),
            'create' => Pages\CreateMedicalRecord::route('/create'),
            'view' => Pages\ViewMedicalRecord::route('/{record}'),
            'edit' => Pages\EditMedicalRecord::route('/{record}/edit'),
        ];
    }
}