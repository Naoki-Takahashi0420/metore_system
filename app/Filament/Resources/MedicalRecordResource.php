<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MedicalRecordResource\Pages;
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
                                            ->options(function () {
                                                $user = auth()->user();
                                                
                                                $query = Customer::query();
                                                
                                                // スーパーアドミンは全顧客にアクセス可能
                                                if ($user->hasRole('super_admin')) {
                                                    // 全顧客
                                                } elseif ($user->hasRole('owner')) {
                                                    // オーナーは管理店舗の予約がある顧客
                                                    $storeIds = $user->manageableStores()->pluck('stores.id')->toArray();
                                                    $query->whereHas('reservations', function ($q) use ($storeIds) {
                                                        $q->whereIn('store_id', $storeIds);
                                                    });
                                                } else {
                                                    // 店長・スタッフは自店舗の予約がある顧客のみ
                                                    if ($user->store_id) {
                                                        $query->whereHas('reservations', function ($q) use ($user) {
                                                            $q->where('store_id', $user->store_id);
                                                        });
                                                    } else {
                                                        return [];
                                                    }
                                                }
                                                
                                                return $query->get()->mapWithKeys(function ($customer) {
                                                    $name = ($customer->last_name ?? '') . ' ' . ($customer->first_name ?? '') . ' (' . ($customer->phone ?? '') . ')';
                                                    return [$customer->id => $name];
                                                });
                                            })
                                            ->searchable()
                                            ->required(),
                                        
                                        Forms\Components\Select::make('reservation_id')
                                            ->label('予約')
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
                                                
                                                // 予約が未選択の場合は全スタッフを表示
                                                return \App\Models\User::where('is_active', true)
                                                    ->pluck('name', 'name');
                                            })
                                            ->default(Auth::user()->name)
                                            ->searchable()
                                            ->reactive()
                                            ->required(),
                                        
                                        Forms\Components\DatePicker::make('treatment_date')
                                            ->label('施術日')
                                            ->default(now())
                                            ->required(),
                                    ]),
                            ]),
                        
                        // 顧客管理情報タブ（常に表示）
                        Forms\Components\Tabs\Tab::make('顧客管理情報')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('payment_method')
                                            ->label('支払い方法')
                                            ->options(function ($get) {
                                                // 予約から店舗情報を取得
                                                $reservationId = $get('reservation_id');
                                                if ($reservationId) {
                                                    $reservation = Reservation::with(['store'])->find($reservationId);
                                                    if ($reservation && $reservation->store && $reservation->store->payment_methods) {
                                                        $options = [];
                                                        foreach ($reservation->store->payment_methods as $method) {
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
                                                }
                                                
                                                // デフォルト（店舗未選択時）
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
                                                
                                                Forms\Components\TextInput::make('duration')
                                                    ->label('時間（分）')
                                                    ->numeric()
                                                    ->default(function ($get) {
                                                        // 予約からメニュー情報を取得して時間を自動設定
                                                        $reservationId = $get('../../reservation_id');
                                                        if ($reservationId) {
                                                            $reservation = Reservation::with(['menu'])->find($reservationId);
                                                            if ($reservation && $reservation->menu && $reservation->menu->duration_minutes) {
                                                                return $reservation->menu->duration_minutes;
                                                            }
                                                        }
                                                        return 60; // デフォルト60分
                                                    })
                                                    ->suffix('分'),
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
                        
                        // 予約履歴タブ（一時的にコメントアウト - 無限読み込み問題の原因調査のため）
                        /*
                        Forms\Components\Tabs\Tab::make('予約履歴')
                            ->schema([
                                Forms\Components\Section::make('顧客の予約履歴')
                                    ->schema([
                                        Forms\Components\Placeholder::make('reservation_history')
                                            ->label('')
                                            ->content(function ($record) {
                                                if (!$record || !$record->customer) {
                                                    return '顧客が選択されていません。';
                                                }
                                                
                                                $customer = $record->customer;
                                                
                                                // 最適化: 必要な列のみ選択し、10件に制限
                                                $reservations = $customer->reservations()
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
                                                    ->limit(10) // 10件に削減
                                                    ->get();
                                                
                                                if ($reservations->isEmpty()) {
                                                    return '予約履歴がありません。';
                                                }
                                                
                                                // 簡素化: 複雑な分類を削除し、シンプルなリスト表示
                                                $html = '<div class="space-y-3">';
                                                $html .= '<div class="text-sm text-gray-600 mb-3">最新10件の予約履歴</div>';
                                                
                                                foreach ($reservations as $reservation) {
                                                    // ステータス表示（日本語ラベル保持）
                                                    $statusBadge = match($reservation->status) {
                                                        'confirmed' => '<span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">確定</span>',
                                                        'booked' => '<span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">予約済</span>',
                                                        'completed' => '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">完了</span>',
                                                        'pending' => '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">保留</span>',
                                                        'cancelled' => '<span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs">キャンセル</span>',
                                                        'canceled' => '<span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs">キャンセル</span>',
                                                        'no_show' => '<span class="px-2 py-1 bg-orange-100 text-orange-800 rounded text-xs">無断キャンセル</span>',
                                                        default => '<span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs">' . $reservation->status . '</span>'
                                                    };
                                                    
                                                    $html .= '<div class="flex justify-between items-center p-3 bg-white border rounded">';
                                                    $html .= '<div class="flex-1">';
                                                    $html .= '<div class="font-medium">' . $reservation->reservation_date->format('Y/m/d') . ' ' . date('H:i', strtotime($reservation->start_time)) . '</div>';
                                                    $html .= '<div class="text-sm text-gray-600">' . ($reservation->menu->name ?? '-') . ' / ' . ($reservation->store->name ?? '-') . '</div>';
                                                    if ($reservation->staff) {
                                                        $html .= '<div class="text-xs text-gray-500">担当: ' . $reservation->staff->name . '</div>';
                                                    }
                                                    $html .= '</div>';
                                                    $html .= '<div class="text-right">';
                                                    $html .= $statusBadge;
                                                    if ($reservation->total_amount) {
                                                        $html .= '<div class="text-sm mt-1">¥' . number_format($reservation->total_amount) . '</div>';
                                                    }
                                                    $html .= '</div>';
                                                    $html .= '</div>';
                                                }
                                                
                                                $html .= '</div>';
                                                
                                                return new \Illuminate\Support\HtmlString($html);
                                            })
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        */
                        
                        // 画像タブ
                        Forms\Components\Tabs\Tab::make('画像')
                            ->schema([
                                Forms\Components\Repeater::make('attachedImages')
                                    ->label('添付画像')
                                    ->relationship()
                                    ->schema([
                                        // 既存画像表示用
                                        Forms\Components\Placeholder::make('existing_image_display')
                                            ->label('既存画像')
                                            ->content(function ($record) {
                                                if (!$record || !$record->exists || !$record->file_path) {
                                                    return '';
                                                }
                                                
                                                $imageUrl = \Storage::disk('public')->url($record->file_path);
                                                $fileName = basename($record->file_path);
                                                
                                                return new \Illuminate\Support\HtmlString('
                                                    <div class="border border-gray-300 rounded-lg p-4 bg-gray-50">
                                                        <div class="flex items-center space-x-4">
                                                            <div class="flex-shrink-0">
                                                                <img src="' . $imageUrl . '" 
                                                                     alt="既存画像" 
                                                                     class="w-20 h-20 object-cover rounded-md border border-gray-200"
                                                                     onclick="window.open(\'' . $imageUrl . '\', \'_blank\')"
                                                                     style="cursor: pointer;">
                                                            </div>
                                                            <div class="flex-1">
                                                                <p class="text-sm font-medium text-gray-900">現在の画像ファイル</p>
                                                                <p class="text-sm text-gray-500">' . $fileName . '</p>
                                                                <p class="text-xs text-blue-600 mt-1">クリックで拡大表示</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ');
                                            })
                                            ->visible(fn ($record) => $record && $record->exists && $record->file_path)
                                            ->columnSpan(2),
                                        
                                        // ファイルアップロード用（新規・置き換え）
                                        Forms\Components\FileUpload::make('file_path')
                                            ->label(fn ($record) => ($record && $record->exists) ? '新しい画像ファイル（置き換える場合）' : '画像ファイル')
                                            ->image()
                                            ->directory('medical-records')
                                            ->disk('public')
                                            ->maxSize(15360)
                                            ->required(fn ($record) => !$record || !$record->exists)
                                            ->helperText(fn ($record) => ($record && $record->exists) ? '新しいファイルを選択すると既存の画像が置き換えられます' : null)
                                            ->columnSpan(2),
                                        
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('title')
                                                    ->label('タイトル')
                                                    ->placeholder('画像のタイトル'),
                                                
                                                Forms\Components\Select::make('image_type')
                                                    ->label('画像タイプ')
                                                    ->options([
                                                        'before' => '施術前',
                                                        'after' => '施術後',
                                                        'progress' => '経過',
                                                        'reference' => '参考',
                                                        'other' => 'その他',
                                                    ])
                                                    ->default('other'),
                                            ])
                                            ->columnSpan(2),
                                        
                                        Forms\Components\Textarea::make('description')
                                            ->label('説明')
                                            ->placeholder('画像の説明を入力')
                                            ->rows(2)
                                            ->columnSpan(2),
                                        
                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\TextInput::make('display_order')
                                                    ->label('表示順')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->minValue(0),
                                                
                                                Forms\Components\Toggle::make('is_visible_to_customer')
                                                    ->label('顧客に表示')
                                                    ->default(true)
                                                    ->helperText('ONにすると顧客側でも表示されます'),
                                                
                                                Forms\Components\Placeholder::make('file_info')
                                                    ->label('ファイル情報')
                                                    ->content(function ($record) {
                                                        if (!$record || !$record->exists) return '新規画像';
                                                        if ($record->file_path) {
                                                            return '既存ファイル: ' . basename($record->file_path);
                                                        }
                                                        return $record->formatted_file_size ?? '-';
                                                    }),
                                            ])
                                            ->columnSpan(2),
                                    ])
                                    ->columns(2)
                                    ->collapsible()
                                    ->defaultItems(0)
                                    ->addActionLabel('画像を追加')
                                    ->reorderable('display_order')
                                    ->grid(1),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.last_name')
                    ->label('顧客名')
                    ->formatStateUsing(fn ($record) => $record->customer ? (($record->customer->last_name ?? '') . ' ' . ($record->customer->first_name ?? '')) : '-')
                    ->searchable(['customers.last_name', 'customers.first_name'])
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('treatment_date')
                    ->label('施術日')
                    ->date('Y/m/d')
                    ->sortable(),
                
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
                Tables\Filters\SelectFilter::make('store')
                    ->label('店舗')
                    ->options(\App\Models\Store::where('is_active', true)->pluck('name', 'id'))
                    ->query(function ($query, $data) {
                        if ($data['value']) {
                            return $query->whereHas('customer', function ($q) use ($data) {
                                $q->whereHas('reservations', function ($r) use ($data) {
                                    $r->where('store_id', $data['value']);
                                });
                            });
                        }
                        return $query;
                    })
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
        if ($user->hasRole('owner')) {
            $storeIds = $user->manageableStores()->pluck('stores.id')->toArray();
            return $query->whereHas('customer.reservations', function ($q) use ($storeIds) {
                $q->whereIn('store_id', $storeIds);
            });
        }

        // 店長・スタッフは自店舗に関連するカルテのみ表示
        if ($user->hasRole(['manager', 'staff']) && $user->store_id) {
            return $query->whereHas('customer.reservations', function ($q) use ($user) {
                $q->where('store_id', $user->store_id);
            });
        }

        // 該当ロールがない場合は空の結果
        return $query->whereRaw('1 = 0');
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