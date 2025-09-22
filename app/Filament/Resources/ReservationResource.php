<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservationResource\Pages;
use App\Models\Reservation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = '予約管理';

    protected static ?string $modelLabel = '予約';

    protected static ?string $pluralModelLabel = '予約';
    
    protected static ?string $recordTitleAttribute = 'reservation_number';

    protected static ?int $navigationSort = 3;
    
    protected static ?string $navigationGroup = '予約管理';

    protected static function checkAvailability($date, $startTime, $endTime, $staffId = null): array
    {
        $query = Reservation::where('reservation_date', $date)
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('start_time', [$startTime, $endTime])
                  ->orWhereBetween('end_time', [$startTime, $endTime])
                  ->orWhere(function ($q2) use ($startTime, $endTime) {
                      $q2->where('start_time', '<=', $startTime)
                         ->where('end_time', '>=', $endTime);
                  });
            });

        if ($staffId) {
            $query->where('staff_id', $staffId);
            $count = $query->count();
            
            if ($count > 0) {
                return [
                    'is_available' => false,
                    'message' => "選択したスタッフは指定時間に{$count}件の予約があります"
                ];
            }
        } else {
            $count = $query->count();
            
            if ($count >= 3) { // 同時刻に3件以上の予約がある場合は混雑
                return [
                    'is_available' => false,
                    'message' => "指定時間は混雑しています（{$count}件の予約あり）"
                ];
            }
        }

        return [
            'is_available' => true,
            'message' => '予約可能です'
        ];
    }

    public static function getSimpleFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('reservation_number')
                ->label('予約番号')
                ->disabled(),
            Forms\Components\Select::make('status')
                ->label('ステータス')
                ->options([
                    'booked' => '予約済み',
                    'completed' => '完了',
                    'no_show' => '来店なし',
                    'cancelled' => 'キャンセル',
                ])
                ->required(),
            Forms\Components\DatePicker::make('reservation_date')
                ->label('予約日')
                ->required(),
            Forms\Components\TimePicker::make('start_time')
                ->label('開始時刻')
                ->required(),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\TextInput::make('reservation_number')
                            ->label('予約番号')
                            ->disabled(),
                        Forms\Components\Select::make('store_id')
                            ->label('店舗')
                            ->relationship('store', 'name')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                // 店舗が変更されたら予約日の制限をリセット
                                if ($state) {
                                    $store = \App\Models\Store::find($state);
                                    if ($store && !$store->allow_same_day_booking) {
                                        // 当日予約不可の場合、明日を設定
                                        $currentDate = $get('reservation_date');
                                        if ($currentDate && \Carbon\Carbon::parse($currentDate)->isToday()) {
                                            $set('reservation_date', now()->addDay());
                                        }
                                    }
                                }
                            }),
                        Forms\Components\Select::make('customer_id')
                            ->label('顧客')
                            ->relationship('customer', 'last_name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => mb_convert_encoding(($record->last_name ?? '') . ' ' . ($record->first_name ?? '') . ' (' . ($record->phone ?? '') . ')', 'UTF-8', 'auto'))
                            ->searchable(['last_name', 'first_name', 'phone', 'last_name_kana', 'first_name_kana'])
                            ->placeholder('電話番号、名前、カナで検索')
                            ->helperText('電話番号の一部でも検索可能です')
                            ->required()
                            ->reactive()
                            ->createOptionForm([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('last_name')
                                            ->label('姓')
                                            ->required(),
                                        Forms\Components\TextInput::make('first_name')
                                            ->label('名')
                                            ->required(),
                                        Forms\Components\TextInput::make('last_name_kana')
                                            ->label('姓（カナ）'),
                                        Forms\Components\TextInput::make('first_name_kana')
                                            ->label('名（カナ）'),
                                        Forms\Components\TextInput::make('phone')
                                            ->label('電話番号')
                                            ->tel()
                                            ->required(),
                                        Forms\Components\TextInput::make('email')
                                            ->label('メールアドレス')
                                            ->email(),
                                    ]),
                            ])
                            ->createOptionAction(function ($action) {
                                return $action
                                    ->modalHeading('新規顧客登録')
                                    ->modalButton('登録')
                                    ->modalWidth('lg');
                            })
                            ->afterStateUpdated(function ($state, $set, $get) {
                                if ($state) {
                                    // 顧客の最新カルテから推奨日を取得
                                    $latestRecord = \App\Models\MedicalRecord::where('customer_id', $state)
                                        ->whereNotNull('next_visit_date')
                                        ->where('reservation_status', 'pending')
                                        ->orderBy('record_date', 'desc')
                                        ->first();
                                    
                                    if ($latestRecord) {
                                        // 推奨日をフォームに反映
                                        $set('reservation_date', $latestRecord->next_visit_date);
                                    }
                                }
                            }),
                        Forms\Components\Select::make('menu_id')
                            ->label('メニュー')
                            ->options(function () {
                                $menus = \App\Models\Menu::where('is_available', true)
                                    ->where('is_visible_to_customer', true)
                                    ->with('category')
                                    ->orderBy('is_subscription', 'desc')
                                    ->orderBy('sort_order')
                                    ->orderBy('name')
                                    ->get();

                                $options = [];
                                foreach ($menus as $menu) {
                                    $label = '';

                                    // アイコンを追加
                                    if ($menu->is_subscription) {
                                        $label .= '🔄 ';  // サブスクアイコン
                                    } else {
                                        $label .= '📍 ';  // 通常メニューアイコン
                                    }

                                    $label .= $menu->name;

                                    // 時間と料金を見やすく表示
                                    $details = [];
                                    if ($menu->duration_minutes) {
                                        $details[] = $menu->duration_minutes . '分';
                                    }
                                    if ($menu->is_subscription) {
                                        $details[] = 'サブスク';
                                    } elseif ($menu->price) {
                                        $details[] = '¥' . number_format($menu->price);
                                    }

                                    if (!empty($details)) {
                                        $label .= ' (' . implode(' / ', $details) . ')';
                                    }

                                    // カテゴリー名をキーに含める（検索用）
                                    if ($menu->category) {
                                        $label = '【' . $menu->category->name . '】 ' . $label;
                                    }

                                    $options[$menu->id] = $label;
                                }

                                return $options;
                            })
                            ->searchable()
                            ->searchPrompt('メニュー名、時間（60、90）、「サブスク」で検索')
                            ->placeholder('クリックして全メニューを表示')
                            ->native(false)  // ネイティブセレクトを無効にして検索を強化
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state) {
                                    $menu = \App\Models\Menu::find($state);
                                    if ($menu) {
                                        // メニューの時間から終了時刻を自動計算
                                        $startTime = request()->get('start_time');
                                        if ($startTime && $menu->duration_minutes) {
                                            $endTime = \Carbon\Carbon::parse($startTime)
                                                ->addMinutes($menu->duration_minutes)
                                                ->format('H:i');
                                            $set('end_time', $endTime);
                                        }
                                    }
                                }
                            }),
                        Forms\Components\Placeholder::make('option_menus_info')
                            ->label('選択されたオプション')
                            ->content(function ($record) {
                                if (!$record || !$record->optionMenus->count()) {
                                    return 'なし';
                                }
                                
                                return $record->optionMenus->map(function ($option) {
                                    return $option->name . ' (+¥' . number_format($option->pivot->price) . ', +' . $option->pivot->duration . '分)';
                                })->join("\n");
                            })
                            ->columnSpanFull(),
                        Forms\Components\Select::make('staff_id')
                            ->label('担当スタッフ')
                            ->relationship('staff', 'name', function ($query) {
                                return $query->where('is_active_staff', true);
                            })
                            ->placeholder('スタッフを選択（任意）')
                            ->searchable()
                            ->reactive()
                            ->visible(function ($get) {
                                $menuId = $get('menu_id');
                                if (!$menuId) return true; // メニュー未選択時は表示
                                $menu = \App\Models\Menu::find($menuId);
                                return $menu && $menu->requires_staff;
                            })
                            ->afterStateUpdated(function ($state, $set, $get) {
                                // スタッフの空き時間をチェック
                                if ($state && $get('reservation_date') && $get('start_time')) {
                                    $hasConflict = \App\Models\Reservation::where('staff_id', $state)
                                        ->where('reservation_date', $get('reservation_date'))
                                        ->where('status', '!=', 'cancelled')
                                        ->where(function ($query) use ($get) {
                                            $query->whereBetween('start_time', [$get('start_time'), $get('end_time')])
                                                  ->orWhereBetween('end_time', [$get('start_time'), $get('end_time')]);
                                        })
                                        ->exists();
                                    
                                    if ($hasConflict) {
                                        Notification::make()
                                            ->warning()
                                            ->title('スタッフの予約が重複しています')
                                            ->body('選択した時間帯に既に予約があります')
                                            ->send();
                                    }
                                }
                            }),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('予約詳細')
                    ->schema([
                        Forms\Components\Placeholder::make('recommended_info')
                            ->label('推奨来院情報')
                            ->content(function ($get) {
                                $customerId = $get('customer_id');
                                if (!$customerId) {
                                    return '顧客を選択してください';
                                }
                                
                                $latestRecord = \App\Models\MedicalRecord::where('customer_id', $customerId)
                                    ->whereNotNull('next_visit_date')
                                    ->where('reservation_status', 'pending')
                                    ->orderBy('record_date', 'desc')
                                    ->first();
                                
                                if ($latestRecord) {
                                    $recommendedDate = \Carbon\Carbon::parse($latestRecord->next_visit_date);
                                    $recordDate = \Carbon\Carbon::parse($latestRecord->record_date);
                                    $daysFromNow = \Carbon\Carbon::now()->diffInDays($recommendedDate, false);
                                    
                                    $urgency = '';
                                    if ($daysFromNow < 0) {
                                        $urgency = '⚠️ 推奨日を過ぎています';
                                    } elseif ($daysFromNow <= 7) {
                                        $urgency = '🔥 推奨日が近づいています';
                                    } else {
                                        $urgency = '📅 推奨日まで余裕があります';
                                    }
                                    
                                    return "💡 推奨日: {$recommendedDate->format('Y年m月d日')} (約{$daysFromNow}日後)\n📝 記録日: {$recordDate->format('Y年m月d日')}\n{$urgency}";
                                }
                                
                                return '⚪ この顧客の推奨日情報はありません';
                            })
                            ->columnSpanFull(),
                        
                        Forms\Components\DatePicker::make('reservation_date')
                            ->label('予約日')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                // 日付変更時に空き状況を確認
                                if ($state && $get('start_time') && $get('end_time')) {
                                    $availableSlots = static::checkAvailability(
                                        $state,
                                        $get('start_time'),
                                        $get('end_time'),
                                        $get('staff_id')
                                    );
                                    
                                    if (!$availableSlots['is_available']) {
                                        Notification::make()
                                            ->warning()
                                            ->title('予約が混雑しています')
                                            ->body($availableSlots['message'])
                                            ->send();
                                    }
                                }
                            })
                            ->minDate(function ($get, $record) {
                                // 編集時（既存レコードがある場合）は過去の日付も選択可能
                                if ($record !== null) {
                                    return null;
                                }

                                $storeId = $get('store_id');
                                if (!$storeId) {
                                    return today();
                                }

                                $store = \App\Models\Store::find($storeId);
                                if (!$store) {
                                    return today();
                                }

                                // 当日予約が不可の場合は明日から
                                if (!$store->allow_same_day_booking) {
                                    return today()->addDay();
                                }

                                return today();
                            })
                            ->maxDate(function ($get) {
                                $storeId = $get('store_id');
                                if (!$storeId) {
                                    return today()->addDays(90);
                                }
                                
                                $store = \App\Models\Store::find($storeId);
                                if (!$store) {
                                    return today()->addDays(90);
                                }
                                
                                // 店舗の最大予約受付日数を適用
                                return today()->addDays($store->max_advance_days ?? 90);
                            })
                            ->default(function ($get) {
                                $storeId = $get('store_id');
                                if (!$storeId) {
                                    return today();
                                }
                                
                                $store = \App\Models\Store::find($storeId);
                                if ($store && !$store->allow_same_day_booking) {
                                    return today()->addDay();
                                }
                                
                                return today();
                            })
                            ->helperText(function ($get) {
                                $customerId = $get('customer_id');
                                if (!$customerId) {
                                    return '顧客を選択すると推奨日が表示されます';
                                }
                                
                                $latestRecord = \App\Models\MedicalRecord::where('customer_id', $customerId)
                                    ->whereNotNull('next_visit_date')
                                    ->where('reservation_status', 'pending')
                                    ->orderBy('record_date', 'desc')
                                    ->first();
                                
                                if ($latestRecord) {
                                    $recommendedDate = \Carbon\Carbon::parse($latestRecord->next_visit_date);
                                    $recordDate = \Carbon\Carbon::parse($latestRecord->record_date);
                                    return "💡 推奨日: {$recommendedDate->format('Y年m月d日')} ({$recordDate->format('m/d')}のカルテより)";
                                }
                                
                                return 'この顧客の推奨日情報はありません';
                            }),
                        Forms\Components\TimePicker::make('start_time')
                            ->label('開始時刻')
                            ->required()
                            ->reactive()
                            ->seconds(false)
                            ->afterStateUpdated(function ($state, $set, $get) {
                                // メニューが選択されている場合、終了時刻を自動計算
                                if ($state && $get('menu_id')) {
                                    $menu = \App\Models\Menu::find($get('menu_id'));
                                    if ($menu && $menu->duration_minutes) {
                                        $endTime = \Carbon\Carbon::parse($state)
                                            ->addMinutes($menu->duration_minutes)
                                            ->format('H:i');
                                        $set('end_time', $endTime);
                                    }
                                }
                            })
                            ->helperText(function ($get) {
                                $storeId = $get('store_id');
                                $reservationDate = $get('reservation_date');
                                
                                if ($storeId && $reservationDate && \Carbon\Carbon::parse($reservationDate)->isToday()) {
                                    $store = \App\Models\Store::find($storeId);
                                    if ($store) {
                                        $minBookingHours = $store->min_booking_hours ?? 1;
                                        return "※ {$minBookingHours}時間前までの予約が必要です";
                                    }
                                }
                                
                                return null;
                            }),
                        Forms\Components\TimePicker::make('end_time')
                            ->label('終了時刻')
                            ->required()
                            ->seconds(false),
                        Forms\Components\TextInput::make('guest_count')
                            ->label('来店人数')
                            ->numeric()
                            ->default(1)
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('ステータス')
                            ->options([
                                'booked' => '予約済み',
                                'completed' => '完了',
                                'no_show' => '来店なし',
                                'cancelled' => 'キャンセル',
                            ])
                            ->default('booked')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set) {
                                // キャンセル時に現在日時を記録
                                if ($state === 'cancelled') {
                                    $set('cancelled_at', now());
                                }
                            }),
                        Forms\Components\Textarea::make('cancel_reason')
                            ->label('キャンセル理由')
                            ->placeholder('電話にて顧客都合でキャンセル、体調不良など')
                            ->visible(fn ($get) => $get('status') === 'cancelled')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('source')
                            ->label('予約経路')
                            ->options([
                                'website' => 'ウェブサイト',
                                'phone' => '電話',
                                'walk_in' => '来店',
                                'admin' => '管理者',
                            ])
                            ->default('website'),
                    ])
                    ->columns(3),

                // 支払い情報は美容サロンには複雑すぎるため非表示
                Forms\Components\Hidden::make('total_amount')->default(0),
                Forms\Components\Hidden::make('deposit_amount')->default(0),
                Forms\Components\Hidden::make('payment_method')->default('cash'),
                Forms\Components\Hidden::make('payment_status')->default('unpaid'),

                Forms\Components\Section::make('その他')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('お客様備考')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('internal_notes')
                            ->label('内部メモ')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('cancel_reason')
                            ->label('キャンセル理由')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reservation_number')
                    ->label('予約番号')
                    ->searchable(),
                Tables\Columns\TextColumn::make('reservation_date')
                    ->label('予約日')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('開始時刻')
                    ->time('H:i'),
                Tables\Columns\TextColumn::make('customer.last_name')
                    ->label('顧客')
                    ->formatStateUsing(fn ($record) => $record->customer->last_name . ' ' . $record->customer->first_name)
                    ->searchable(['customers.last_name', 'customers.first_name']),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('店舗')
                    ->searchable(),
                Tables\Columns\TextColumn::make('menu.name')
                    ->label('メニュー'),
                Tables\Columns\TextColumn::make('option_menus')
                    ->label('オプション')
                    ->formatStateUsing(function ($record) {
                        $options = $record->optionMenus;
                        if ($options->isEmpty()) {
                            return 'なし';
                        }
                        return $options->map(function ($option) {
                            return $option->name . ' (+¥' . number_format($option->pivot->price) . ')';
                        })->join(', ');
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('notes')
                    ->label('お客様備考')
                    ->limit(50)
                    ->placeholder('なし')
                    ->tooltip(function ($record) {
                        return $record->notes ? $record->notes : null;
                    })
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('ステータス')
                    ->colors([
                        'primary' => 'booked',
                        'success' => 'completed',
                        'warning' => 'no_show',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'booked' => '予約済み',
                        'completed' => '完了',
                        'no_show' => '来店なし',
                        'cancelled' => 'キャンセル',
                        'pending' => '予約済み',  // 旧データ用
                        'confirmed' => '予約済み', // 旧データ用
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('金額')
                    ->money('JPY'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日時')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('ステータス')
                    ->options([
                        'booked' => '予約済み',
                        'completed' => '完了',
                        'no_show' => '来店なし',
                        'cancelled' => 'キャンセル',
                    ]),
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('店舗')
                    ->relationship('store', 'name'),
                Tables\Filters\Filter::make('reservation_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('開始日'),
                        Forms\Components\DatePicker::make('to')
                            ->label('終了日'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('reservation_date', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('reservation_date', '<=', $date),
                            );
                    }),
            ])
            ->actionsColumnLabel('操作')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('詳細'),
                Tables\Actions\Action::make('reschedule')
                    ->label('日程変更')
                    ->icon('heroicon-o-calendar')
                    ->color('info')
                    ->url(fn ($record) => route('admin.reservations.reschedule', $record))
                    ->openUrlInNewTab(false)
                    ->visible(fn ($record) => $record->status === 'booked'),
                Tables\Actions\Action::make('edit')
                    ->label('編集')
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->action(function ($record) {
                        // JavaScriptでページ遷移
                        return redirect("/admin/reservations/{$record->id}/edit");
                    }),
                Tables\Actions\Action::make('complete')
                    ->label('完了')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('予約を完了にする')
                    ->modalDescription('この予約を完了（来店済み）にマークします。')
                    ->action(function ($record) {
                        $record->update(['status' => 'completed']);

                        // サブスクリプション利用回数を更新
                        $customer = $record->customer;
                        if ($customer) {
                            $subscription = $customer->activeSubscription;
                            if ($subscription) {
                                $subscription->recordVisit();
                                
                                Notification::make()
                                    ->success()
                                    ->title('サブスク利用回数を更新しました')
                                    ->body("残り回数: {$subscription->remaining_visits}回")
                                    ->send();
                            }
                        }
                        
                        // カルテが既に存在するかチェック
                        $existingRecord = \App\Models\MedicalRecord::where('reservation_id', $record->id)->first();
                        
                        if ($existingRecord) {
                            // 既存のカルテを編集
                            $url = "/admin/medical-records/{$existingRecord->id}/edit";
                            $message = '既存のカルテを確認・編集してください';
                            $buttonLabel = 'カルテを確認';
                        } else {
                            // 新しいカルテを作成
                            $url = "/admin/medical-records/create?customer_id={$record->customer_id}&reservation_id={$record->id}";
                            $message = '続いてカルテを作成してください';
                            $buttonLabel = 'カルテを作成';
                        }
                        
                        Notification::make()
                            ->success()
                            ->title('予約を完了しました')
                            ->body($message)
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('print_receipt')
                                    ->label('領収証を印刷')
                                    ->icon('heroicon-m-printer')
                                    ->color('gray')
                                    ->url("/receipt/reservation/{$record->id}", shouldOpenInNewTab: true),
                                \Filament\Notifications\Actions\Action::make('create_medical_record')
                                    ->label($buttonLabel)
                                    ->url($url)
                                    ->button()
                            ])
                            ->persistent()
                            ->send();
                    })
                    ->visible(fn ($record) => $record->status === 'booked'),
                Tables\Actions\Action::make('no_show')
                    ->label('来店なし')
                    ->icon('heroicon-o-user-minus')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('来店なしにする')
                    ->modalDescription('この予約を来店なし（ノーショー）にマークします。')
                    ->action(fn ($record) => $record->update(['status' => 'no_show']))
                    ->visible(fn ($record) => $record->status === 'booked'),
                Tables\Actions\Action::make('cancel')
                    ->label('キャンセル')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('cancel_reason')
                            ->label('キャンセル理由')
                            ->required()
                            ->placeholder('顧客からの電話連絡、体調不良など'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => 'cancelled',
                            'cancel_reason' => $data['cancel_reason'],
                            'cancelled_at' => now(),
                        ]);
                    })
                    ->visible(fn ($record) => $record->status === 'booked'),
                Tables\Actions\Action::make('restore')
                    ->label('予約を復元')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('予約を復元')
                    ->modalDescription('この予約を予約済みステータスに戻します。')
                    ->action(fn ($record) => $record->update(['status' => 'booked']))
                    ->visible(fn ($record) => in_array($record->status, ['cancelled', 'no_show'])),
                Tables\Actions\Action::make('move_to_sub')
                    ->label('サブラインへ移動')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('サブラインへ移動')
                    ->modalDescription('この予約をサブラインに移動します。メインラインの枠が空きます。')
                    ->action(function ($record) {
                        $record->moveToSubLine();
                    })
                    ->visible(fn ($record) => $record->line_type === 'main' && $record->status === 'booked'),
                Tables\Actions\Action::make('move_to_main')
                    ->label('メインラインへ戻す')
                    ->icon('heroicon-o-arrow-left-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('メインラインへ戻す')
                    ->modalDescription('この予約をメインラインに戻します。')
                    ->action(function ($record) {
                        $record->moveToMainLine();
                    })
                    ->visible(fn ($record) => $record->line_type === 'sub' && $record->status === 'booked'),
                Tables\Actions\Action::make('create_medical_record')
                    ->label('カルテ作成')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->url(fn ($record) => route('filament.admin.resources.medical-records.create', [
                        'customer_id' => $record->customer_id,
                        'reservation_id' => $record->id
                    ]))
                    ->visible(fn ($record) => $record->status === 'completed'),
                Tables\Actions\Action::make('receipt')
                    ->label('領収証')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->url(fn ($record) => "/receipt/reservation/{$record->id}")
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->status === 'completed'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('reservation_date', 'desc');
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
            'index' => Pages\ListReservations::route('/'),
            'calendar' => Pages\CalendarView::route('/calendar'),
            'create' => Pages\CreateReservation::route('/create'),
            'view' => Pages\ViewReservation::route('/{record}'),
            'edit' => Pages\EditReservation::route('/{record}/edit'),
        ];
    }
    
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }
        
        // スーパーアドミンは全予約を表示
        if ($user->hasRole('super_admin')) {
            return $query;
        }
        
        // オーナーは管理可能店舗の予約のみ表示
        if ($user->hasRole('owner')) {
            $manageableStoreIds = $user->manageableStores()->pluck('stores.id');
            return $query->whereIn('store_id', $manageableStoreIds);
        }
        
        // 店長・スタッフは所属店舗の予約のみ表示
        if ($user->hasRole(['manager', 'staff'])) {
            if ($user->store_id) {
                return $query->where('store_id', $user->store_id);
            }
            return $query->whereRaw('1 = 0');
        }
        
        return $query->whereRaw('1 = 0');
    }
    
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        // 全ロールで予約一覧の閲覧可能
        return $user->hasRole(['super_admin', 'owner', 'manager', 'staff']);
    }
    
    public static function canView($record): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        
        // スーパーアドミンは全予約を閲覧可能
        if ($user->hasRole('super_admin')) {
            return true;
        }
        
        // オーナーは管理可能店舗の予約のみ閲覧可能
        if ($user->hasRole('owner')) {
            $manageableStoreIds = $user->manageableStores()->pluck('stores.id');
            return in_array($record->store_id, $manageableStoreIds->toArray());
        }
        
        // 店長・スタッフは所属店舗の予約のみ閲覧可能
        if ($user->hasRole(['manager', 'staff'])) {
            return $record->store_id === $user->store_id;
        }
        
        return false;
    }
    
    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        // 全ロールで予約作成可能
        return $user->hasRole(['super_admin', 'owner', 'manager', 'staff']);
    }
    
    public static function canEdit($record): bool
    {
        // 予約編集は予約閲覧権限と同じロジック
        return static::canView($record);
    }
    
    public static function canDelete($record): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        
        // スーパーアドミンとオーナーのみ削除可能
        if ($user->hasRole('super_admin')) {
            return true;
        }
        
        if ($user->hasRole('owner')) {
            $manageableStoreIds = $user->manageableStores()->pluck('stores.id');
            return in_array($record->store_id, $manageableStoreIds->toArray());
        }
        
        return false;
    }

}
