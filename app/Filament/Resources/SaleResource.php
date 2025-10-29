<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Filament\Resources\SaleResource\RelationManagers;
use App\Models\Sale;
use App\Models\Reservation;
use App\Models\Customer;
use App\Models\Menu;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Actions\Action;
use Filament\Notifications\Notification;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-yen';
    
    protected static ?string $navigationLabel = '売上管理';
    
    protected static ?string $modelLabel = '売上';
    
    protected static ?string $pluralModelLabel = '売上';
    
    protected static ?string $navigationGroup = '売上・会計';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('売上基本情報')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('sale_number')
                                    ->label('売上番号')
                                    ->default(fn () => Sale::generateSaleNumber())
                                    ->disabled()
                                    ->dehydrated()
                                    ->required(),
                                Forms\Components\DatePicker::make('sale_date')
                                    ->label('売上日')
                                    ->default(now())
                                    ->native(false)
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(fn ($state, Set $set) => $set('reservation_id', null)),
                                Forms\Components\TimePicker::make('sale_time')
                                    ->label('売上時刻')
                                    ->default(now()->format('H:i'))
                                    ->seconds(false)
                                    ->required(),
                            ]),
                        
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('store_id')
                                    ->label('店舗')
                                    ->relationship('store', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(fn () => auth()->user()->store_id ?? 1)
                                    ->reactive()
                                    ->afterStateUpdated(fn ($state, Set $set) => $set('reservation_id', null)),
                                Forms\Components\Select::make('staff_id')
                                    ->label('担当スタッフ')
                                    ->relationship('staff', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->default(fn () => auth()->id()),
                            ]),
                    ]),
                
                Forms\Components\Section::make('予約・顧客情報')
                    ->schema([
                        Forms\Components\Select::make('reservation_id')
                            ->label(fn (Get $get) => 
                                $get('sale_date') && $get('sale_date') != today()->format('Y-m-d')
                                    ? \Carbon\Carbon::parse($get('sale_date'))->format('n月j日') . 'の予約から選択'
                                    : '本日の予約から選択'
                            )
                            ->placeholder('予約番号・顧客名・電話番号で検索')
                            ->searchable()
                            ->preload()
                            ->helperText(fn (Get $get) => 
                                '売上日と店舗を選択してから予約を検索してください'
                            )
                            ->getOptionLabelUsing(function ($value) {
                                $reservation = Reservation::with(['customer', 'menu', 'store'])->find($value);
                                if (!$reservation) return '';
                                
                                $customerName = $reservation->customer 
                                    ? "{$reservation->customer->last_name}{$reservation->customer->first_name}"
                                    : '顧客不明';
                                $menuName = $reservation->menu ? $reservation->menu->name : 'メニュー不明';
                                $time = \Carbon\Carbon::parse($reservation->start_time)->format('H:i');
                                $storeName = $reservation->store ? $reservation->store->name : '';
                                
                                return "[{$time}] {$reservation->reservation_number} - {$customerName} - {$menuName} ({$storeName})";
                            })
                            ->getSearchResultsUsing(function (string $search, Get $get) {
                                $query = Reservation::with(['customer', 'menu', 'store'])
                                    ->where(function ($q) use ($search) {
                                        $q->where('reservation_number', 'like', "%{$search}%")
                                          ->orWhereHas('customer', function ($query) use ($search) {
                                            $query->where('last_name', 'like', "%{$search}%")
                                                ->orWhere('first_name', 'like', "%{$search}%")
                                                ->orWhere('phone', 'like', "%{$search}%");
                                        });
                                    })
                                    ->whereIn('status', ['booked', 'confirmed', 'pending', 'completed']);
                                
                                // 売上日でフィルター
                                $saleDate = $get('sale_date');
                                if ($saleDate) {
                                    $query->whereDate('reservation_date', $saleDate);
                                } else {
                                    $query->whereDate('reservation_date', today());
                                }
                                
                                // 店舗でフィルター
                                $storeId = $get('store_id');
                                if ($storeId) {
                                    $query->where('store_id', $storeId);
                                }
                                
                                return $query->orderBy('start_time', 'asc')
                                    ->limit(20)
                                    ->get()
                                    ->mapWithKeys(function ($reservation) {
                                        $customerName = $reservation->customer 
                                            ? "{$reservation->customer->last_name}{$reservation->customer->first_name}"
                                            : '顧客不明';
                                        $menuName = $reservation->menu 
                                            ? $reservation->menu->name 
                                            : 'メニュー不明';
                                        $time = \Carbon\Carbon::parse($reservation->start_time)->format('H:i');
                                        $storeName = $reservation->store ? $reservation->store->name : '';
                                        return [$reservation->id => 
                                            "[{$time}] {$reservation->reservation_number} - {$customerName} - {$menuName} ({$storeName})"
                                        ];
                                    });
                            })
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                if ($state) {
                                    $reservation = Reservation::with(['customer', 'menu'])->find($state);
                                    if ($reservation) {
                                        $set('customer_id', $reservation->customer_id);
                                        if ($reservation->menu) {
                                            $price = $reservation->menu->price;
                                            $set('subtotal', $price);
                                            $tax = round($price * 0.1, 0);
                                            $set('tax_amount', $tax);
                                            $set('total_amount', $price + $tax);
                                        }
                                    }
                                }
                            })
                            ->reactive(),
                        
                        Forms\Components\Select::make('customer_id')
                            ->label('顧客')
                            ->relationship('customer', 'last_name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => mb_convert_encoding(($record->last_name ?? '') . ' ' . ($record->first_name ?? ''), 'UTF-8', 'auto'))
                            ->searchable()
                            ->preload(),
                    ]),
                
                Forms\Components\Section::make('商品明細')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('明細')
                            ->relationship()
                            ->schema([
                                Forms\Components\Grid::make(6)
                                    ->schema([
                                        Forms\Components\Select::make('menu_id')
                                            ->label('メニュー')
                                            ->relationship('menu', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                if ($state) {
                                                    $menu = Menu::find($state);
                                                    if ($menu) {
                                                        $set('item_name', $menu->name);
                                                        $set('unit_price', $menu->price);
                                                        $set('quantity', 1);
                                                        $set('amount', $menu->price);
                                                    }
                                                }
                                            })
                                            ->reactive()
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('item_name')
                                            ->label('商品名')
                                            ->required()
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('unit_price')
                                            ->label('単価')
                                            ->numeric()
                                            ->required()
                                            ->prefix('¥')
                                            ->afterStateUpdated(fn (Get $get, Set $set) => 
                                                $set('amount', $get('unit_price') * $get('quantity'))
                                            )
                                            ->reactive(),
                                        Forms\Components\TextInput::make('quantity')
                                            ->label('数量')
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->afterStateUpdated(fn (Get $get, Set $set) => 
                                                $set('amount', $get('unit_price') * $get('quantity'))
                                            )
                                            ->reactive(),
                                    ]),
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('discount_amount')
                                            ->label('割引額')
                                            ->numeric()
                                            ->default(0)
                                            ->prefix('¥'),
                                        Forms\Components\TextInput::make('tax_rate')
                                            ->label('税率(%)')
                                            ->numeric()
                                            ->default(10)
                                            ->suffix('%'),
                                        Forms\Components\TextInput::make('amount')
                                            ->label('金額')
                                            ->numeric()
                                            ->disabled()
                                            ->prefix('¥'),
                                    ]),
                            ])
                            ->defaultItems(1)
                            ->addActionLabel('明細を追加')
                            ->columnSpanFull(),
                    ]),
                
                Forms\Components\Section::make('金額・支払情報')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('subtotal')
                                    ->label('小計')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('¥')
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        $tax = round($state * 0.1, 0);
                                        $set('tax_amount', $tax);
                                        $set('total_amount', $state + $tax - $get('discount_amount'));
                                    }),
                                Forms\Components\TextInput::make('tax_amount')
                                    ->label('消費税')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('¥')
                                    ->required(),
                                Forms\Components\TextInput::make('discount_amount')
                                    ->label('割引額')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('¥')
                                    ->reactive()
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $set('total_amount', $get('subtotal') + $get('tax_amount') - $get('discount_amount'));
                                    }),
                            ]),
                        
                        Forms\Components\TextInput::make('total_amount')
                            ->label('合計金額')
                            ->numeric()
                            ->prefix('¥')
                            ->required()
                            ->disabled()
                            ->dehydrated(),
                        
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('payment_method')
                                    ->label('支払方法')
                                    ->options([
                                        'cash' => '現金',
                                        'credit_card' => 'クレジットカード',
                                        'debit_card' => 'デビットカード',
                                        'paypay' => 'PayPay',
                                        'line_pay' => 'LINE Pay',
                                        'other' => 'その他',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('receipt_number')
                                    ->label('レシート番号'),
                                Forms\Components\Select::make('status')
                                    ->label('ステータス')
                                    ->options([
                                        'completed' => '完了',
                                        'cancelled' => 'キャンセル',
                                        'refunded' => '返金済み',
                                        'partial_refund' => '部分返金',
                                    ])
                                    ->default('completed')
                                    ->required(),
                            ]),
                        
                        Forms\Components\Textarea::make('notes')
                            ->label('備考')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sale_number')
                    ->label('売上番号')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sale_date')
                    ->label('売上日')
                    ->date('Y/m/d')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.full_name')
                    ->label('顧客名')
                    ->getStateUsing(fn ($record) => 
                        $record->customer ? "{$record->customer->last_name} {$record->customer->first_name}" : '-'
                    )
                    ->searchable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('店舗')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('合計金額')
                    ->money('JPY')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('payment_method')
                    ->label('支払方法')
                    ->getStateUsing(fn ($record) => $record->payment_method_label ?? '不明')
                    ->colors([
                        'success' => '現金',
                        'primary' => 'クレジットカード',
                        'warning' => 'PayPay',
                        'info' => 'LINE Pay',
                    ]),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('ステータス')
                    ->getStateUsing(fn ($record) => $record->status_label ?? '不明')
                    ->colors([
                        'success' => '完了',
                        'danger' => 'キャンセル',
                        'warning' => '返金済み',
                    ]),
                Tables\Columns\TextColumn::make('staff.name')
                    ->label('担当')
                    ->toggleable(),
            ])
            ->defaultSort('sale_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('店舗')
                    ->relationship('store', 'name'),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('支払方法')
                    ->options([
                        'cash' => '現金',
                        'credit_card' => 'クレジットカード',
                        'paypay' => 'PayPay',
                        'line_pay' => 'LINE Pay',
                    ]),
                Tables\Filters\Filter::make('sale_date')
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
                                fn (Builder $query, $date): Builder => $query->whereDate('sale_date', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('sale_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('print')
                    ->label('印刷')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->action(function ($record) {
                        Notification::make()
                            ->title('レシート印刷')
                            ->body("売上番号 {$record->sale_number} のレシートを印刷します")
                            ->success()
                            ->send();
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
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    /**
     * 売上の手動作成を無効化
     * 売上は予約から自動計上されるため、手動作成は不要
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * 売上の削除を無効化
     * 売上の取り消しはvoid処理で行うため、直接削除は不可
     */
    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'edit' => Pages\EditSale::route('/{record}/edit'),
            'daily-closing' => Pages\DailyClosing::route('/daily-closing'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereDate('sale_date', today())->count();
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user && !$user->hasRole('staff');
    }
}