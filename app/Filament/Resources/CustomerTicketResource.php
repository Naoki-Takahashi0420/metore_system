<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerTicketResource\Pages;
use App\Models\CustomerTicket;
use App\Models\Customer;
use App\Models\TicketPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerTicketResource extends Resource
{
    protected static ?string $model = CustomerTicket::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = '顧客回数券管理';

    protected static ?string $modelLabel = '顧客回数券';

    protected static ?string $pluralModelLabel = '顧客回数券';

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'customer-tickets';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\Select::make('store_id')
                            ->label('店舗')
                            ->relationship('store', 'name')
                            ->required()
                            ->reactive()
                            ->disabled(fn ($operation) => $operation === 'edit')
                            ->helperText(fn ($operation) => $operation === 'edit' ? '店舗の変更はできません' : 'まず店舗を選択してください'),

                        Forms\Components\Select::make('customer_id')
                            ->label('顧客')
                            ->relationship(
                                name: 'customer',
                                titleAttribute: 'last_name',
                                modifyQueryUsing: fn (Builder $query, callable $get) =>
                                    $query->when(
                                        $get('store_id'),
                                        fn ($q, $storeId) => $q->where(function ($subQ) use ($storeId) {
                                            $subQ->where('store_id', $storeId)
                                                ->orWhereHas('reservations', function ($resQ) use ($storeId) {
                                                    $resQ->where('store_id', $storeId);
                                                });
                                        })
                                    )
                                    ->orderBy('last_name')
                                    ->orderBy('first_name')
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) =>
                                $record->last_name . ' ' . $record->first_name .
                                ($record->phone ? ' (' . $record->phone . ')' : '')
                            )
                            ->searchable(['last_name', 'first_name', 'phone'])
                            ->preload()
                            ->default(fn () => request()->has('customer_id') ? request('customer_id') : null)
                            ->disabled(fn ($operation) => $operation === 'edit')
                            ->required()
                            ->reactive()
                            ->helperText(fn ($operation) => $operation === 'edit' ? '顧客の変更はできません' : '名前または電話番号で検索できます'),

                        Forms\Components\Select::make('ticket_plan_id')
                            ->label('回数券プラン')
                            ->options(function (callable $get) {
                                $storeId = $get('store_id');
                                if (!$storeId) {
                                    return [];
                                }
                                return TicketPlan::where('store_id', $storeId)
                                    ->where('is_active', true)
                                    ->get()
                                    ->mapWithKeys(function ($plan) {
                                        return [$plan->id => $plan->display_name];
                                    });
                            })
                            ->required()
                            ->searchable()
                            ->reactive()
                            ->visible(fn ($operation) => $operation === 'create')
                            ->helperText('店舗を選択すると回数券プランが表示されます')
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if ($state) {
                                    $plan = TicketPlan::find($state);
                                    if ($plan) {
                                        $set('plan_name', $plan->name);
                                        $set('total_count', $plan->ticket_count);
                                        $set('purchase_price', $plan->price);
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('plan_name')
                            ->label('回数券名')
                            ->disabled(fn ($operation) => $operation === 'edit')
                            ->visible(fn ($operation) => $operation === 'edit')
                            ->helperText('プランの変更は新規購入で行ってください'),

                        Forms\Components\Select::make('status')
                            ->label('ステータス')
                            ->options([
                                'active' => '有効',
                                'expired' => '期限切れ',
                                'used_up' => '使い切り',
                                'cancelled' => 'キャンセル',
                            ])
                            ->default('active')
                            ->visible(fn ($operation) => $operation === 'edit')
                            ->helperText('システムが自動管理（手動変更も可能）'),

                        Forms\Components\Checkbox::make('agreement_signed')
                            ->label('同意書記入済み')
                            ->default(false)
                            ->helperText('同意書の記入を受け取った場合のみチェック（任意）'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('回数・金額')
                    ->schema([
                        Forms\Components\TextInput::make('total_count')
                            ->label('総回数')
                            ->numeric()
                            ->suffix('回')
                            ->disabled(fn ($operation) => $operation === 'edit')
                            ->helperText(fn ($operation) => $operation === 'edit' ? 'プランで決定（変更不可）' : 'プランから自動設定'),

                        Forms\Components\TextInput::make('used_count')
                            ->label('利用済み回数')
                            ->numeric()
                            ->suffix('回')
                            ->default(0)
                            ->helperText('手動調整が必要な場合のみ変更'),

                        Forms\Components\Placeholder::make('remaining_count')
                            ->label('残回数')
                            ->content(function (Forms\Get $get): string {
                                $total = $get('total_count') ?? 0;
                                $used = $get('used_count') ?? 0;
                                $remaining = $total - $used;
                                return "{$remaining}回";
                            })
                            ->visible(fn ($operation) => $operation === 'edit'),

                        Forms\Components\TextInput::make('purchase_price')
                            ->label('購入価格')
                            ->numeric()
                            ->prefix('¥')
                            ->disabled(fn ($operation) => $operation === 'edit')
                            ->helperText(fn ($operation) => $operation === 'edit' ? 'プランで決定（変更不可）' : 'プランから自動設定'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('有効期限')
                    ->schema([
                        Forms\Components\DatePicker::make('purchased_at')
                            ->label('購入日')
                            ->displayFormat('Y年m月d日')
                            ->default(now())
                            ->required()
                            ->disabled(fn ($operation) => $operation === 'edit')
                            ->helperText(fn ($operation) => $operation === 'edit' ? '購入時に決定（変更不可）' : '回数券の購入日'),

                        Forms\Components\DatePicker::make('expires_at')
                            ->label('有効期限')
                            ->displayFormat('Y年m月d日')
                            ->helperText('自動計算されます（無期限の場合は空欄）')
                            ->disabled(),

                        Forms\Components\Placeholder::make('days_until_expiry')
                            ->label('有効期限まで')
                            ->content(function ($record): string {
                                if (!$record || !$record->expires_at) {
                                    return '無期限';
                                }

                                $days = $record->days_until_expiry;
                                if ($days < 0) {
                                    return '期限切れ（' . abs($days) . '日経過）';
                                }
                                return "残り{$days}日";
                            })
                            ->visible(fn ($operation) => $operation === 'edit'),

                        Forms\Components\Textarea::make('notes')
                            ->label('メモ・備考')
                            ->rows(3)
                            ->placeholder('内部用のメモや注意事項など')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('決済情報')
                    ->schema([
                        Forms\Components\Select::make('payment_method')
                            ->label('決済方法')
                            ->options(function (callable $get) {
                                $storeId = $get('store_id');
                                if (!$storeId) {
                                    return [
                                        'cash' => '現金',
                                        'credit' => 'クレジットカード',
                                        'bank' => '銀行振込',
                                    ];
                                }

                                $store = \App\Models\Store::find($storeId);
                                if (!$store || !$store->payment_methods) {
                                    return [
                                        'cash' => '現金',
                                        'credit' => 'クレジットカード',
                                        'bank' => '銀行振込',
                                    ];
                                }

                                // 店舗で設定された決済方法を取得
                                $paymentMethods = [];
                                foreach ($store->payment_methods as $index => $method) {
                                    if (isset($method['name']) && !empty($method['name'])) {
                                        $paymentMethods[$method['name']] = $method['name'];
                                    }
                                }

                                return !empty($paymentMethods) ? $paymentMethods : [
                                    'cash' => '現金',
                                    'credit' => 'クレジットカード',
                                    'bank' => '銀行振込',
                                ];
                            })
                            ->searchable()
                            ->helperText('店舗で設定された決済方法から選択'),

                        Forms\Components\TextInput::make('payment_reference')
                            ->label('決済参照番号')
                            ->helperText('外部決済サービスの参照番号など'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.full_name')
                    ->label('顧客名')
                    ->searchable(query: function ($query, $search) {
                        $dbDriver = \DB::connection()->getDriverName();
                        $search = trim($search);

                        return $query->whereHas('customer', function ($q) use ($search, $dbDriver) {
                            $q->where(function ($subQ) use ($search, $dbDriver) {
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
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('ステータス')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'active' => '有効',
                        'expired' => '期限切れ',
                        'used_up' => '使い切り',
                        'cancelled' => 'キャンセル',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'active',
                        'danger' => 'expired',
                        'warning' => 'used_up',
                        'secondary' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('plan_name')
                    ->label('回数券名')
                    ->searchable(),

                Tables\Columns\TextColumn::make('ticketPlan.menu.name')
                    ->label('対象コース')
                    ->sortable()
                    ->searchable()
                    ->default('未設定'),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('店舗')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('remaining_count')
                    ->label('残回数')
                    ->formatStateUsing(fn ($record) =>
                        "{$record->remaining_count}/{$record->total_count}回"
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('有効期限')
                    ->date('Y/m/d')
                    ->sortable()
                    ->placeholder('無期限')
                    ->color(fn ($record) => $record->is_expiring_soon ? 'warning' : null),

                Tables\Columns\TextColumn::make('purchased_at')
                    ->label('購入日')
                    ->date('Y/m/d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('purchase_price')
                    ->label('購入価格')
                    ->money('JPY')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('agreement_signed')
                    ->label('同意書')
                    ->formatStateUsing(fn ($state) => $state ? '取得済み' : '未取得')
                    ->colors([
                        'success' => fn ($state) => $state === true,
                        'danger' => fn ($state) => $state === false,
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('ステータス')
                    ->options([
                        'active' => '有効',
                        'expired' => '期限切れ',
                        'used_up' => '使い切り',
                        'cancelled' => 'キャンセル',
                    ]),

                Tables\Filters\SelectFilter::make('store_id')
                    ->label('店舗')
                    ->options(function () {
                        $user = auth()->user();

                        if ($user->hasRole('super_admin')) {
                            return \App\Models\Store::where('is_active', true)->pluck('name', 'id');
                        } elseif ($user->hasRole('owner')) {
                            return $user->manageableStores()->where('is_active', true)->pluck('name', 'stores.id');
                        } else {
                            return $user->store ? collect([$user->store->id => $user->store->name]) : collect();
                        }
                    })
                    ->query(function ($query, $data) {
                        if (isset($data['value'])) {
                            return $query->where('store_id', $data['value']);
                        }
                        return $query;
                    }),

                Tables\Filters\Filter::make('expiring_soon')
                    ->label('期限間近')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'active')
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '>', now())
                        ->where('expires_at', '<=', now()->addDays(14))),

                Tables\Filters\Filter::make('has_remaining')
                    ->label('残回数あり')
                    ->query(fn (Builder $query): Builder => $query->whereColumn('used_count', '<', 'total_count')),
            ])
            ->actions([
                Tables\Actions\Action::make('use')
                    ->label('1回使用')
                    ->icon('heroicon-o-minus-circle')
                    ->color('warning')
                    ->visible(fn ($record) => $record->canUse())
                    ->requiresConfirmation()
                    ->modalHeading('回数券を1回使用')
                    ->modalDescription(fn ($record) =>
                        "{$record->customer->full_name}様の{$record->plan_name}を1回使用します。\n" .
                        "残回数: {$record->remaining_count}回 → " . ($record->remaining_count - 1) . "回"
                    )
                    ->modalSubmitActionLabel('使用する')
                    ->action(function ($record) {
                        $result = $record->use();

                        if ($result) {
                            \Filament\Notifications\Notification::make()
                                ->title('回数券を使用しました')
                                ->body("残回数: {$record->fresh()->remaining_count}回")
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('使用できませんでした')
                                ->body('回数券が期限切れか使い切りです')
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('refund')
                    ->label('1回返却')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->used_count > 0)
                    ->requiresConfirmation()
                    ->modalHeading('回数券を1回返却')
                    ->modalDescription(fn ($record) =>
                        "{$record->customer->full_name}様の{$record->plan_name}を1回返却します。\n" .
                        "残回数: {$record->remaining_count}回 → " . ($record->remaining_count + 1) . "回"
                    )
                    ->modalSubmitActionLabel('返却する')
                    ->action(function ($record) {
                        $result = $record->refund(null, 1);

                        if ($result) {
                            \Filament\Notifications\Notification::make()
                                ->title('回数券を返却しました')
                                ->body("残回数: {$record->fresh()->remaining_count}回")
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        // スーパーアドミンは全データにアクセス可能
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // オーナーは紐づいた店舗の回数券のみ表示
        if ($user->hasRole('owner')) {
            $storeIds = $user->manageableStores()->pluck('stores.id')->toArray();
            return $query->whereIn('store_id', $storeIds);
        }

        // 店長・スタッフは自店舗の回数券のみ表示
        if ($user->hasRole(['manager', 'staff'])) {
            return $query->where('store_id', $user->store_id);
        }

        // 該当ロールがない場合は空の結果
        return $query->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerTickets::route('/'),
            'create' => Pages\CreateCustomerTicket::route('/create'),
            'view' => Pages\ViewCustomerTicket::route('/{record}'),
            'edit' => Pages\EditCustomerTicket::route('/{record}/edit'),
        ];
    }
}
