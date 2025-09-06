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

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = '予約管理';

    protected static ?string $modelLabel = '予約';

    protected static ?string $pluralModelLabel = '予約';

    protected static ?int $navigationSort = 3;
    
    protected static ?string $navigationGroup = '予約管理';

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
                            ->searchable(['last_name', 'first_name', 'phone'])
                            ->required()
                            ->reactive()
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
                            ->relationship('menu', 'name')
                            ->required(),
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
                            ->relationship('staff', 'name')
                            ->searchable(),
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
                            ->minDate(function ($get) {
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
                            ->required(),
                        Forms\Components\TextInput::make('guest_count')
                            ->label('来店人数')
                            ->numeric()
                            ->default(1)
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('ステータス')
                            ->options([
                                'booked' => '予約確定',
                                'completed' => '完了（来店済み）',
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
                Tables\Columns\BadgeColumn::make('status')
                    ->label('ステータス')
                    ->colors([
                        'primary' => 'booked',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'booked' => '予約確定',
                        'completed' => '完了',
                        'cancelled' => 'キャンセル',
                        'pending' => '予約確定',  // 旧データ用
                        'confirmed' => '予約確定', // 旧データ用
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
                        'booked' => '予約確定',
                        'completed' => '完了（来店済み）',
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
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
                    ->visible(fn ($record) => in_array($record->status, ['booked', 'in_progress'])),
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
                    ->visible(fn ($record) => in_array($record->status, ['completed', 'in_progress'])),
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
            'create' => Pages\CreateReservation::route('/create'),
            'view' => Pages\ViewReservation::route('/{record}'),
            'edit' => Pages\EditReservation::route('/{record}/edit'),
        ];
    }
}