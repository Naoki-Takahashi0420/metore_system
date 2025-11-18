<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FcOrderResource\Pages;
use App\Models\FcOrder;
use App\Models\FcProduct;
use App\Models\FcOrderItem;
use App\Models\Store;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use App\Services\FcNotificationService;

class FcOrderResource extends Resource
{
    protected static ?string $model = FcOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'FC発注管理';

    protected static ?string $modelLabel = 'FC発注';

    protected static ?string $pluralModelLabel = 'FC発注';

    protected static ?string $navigationGroup = 'FC本部管理';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0'); // 認証されていない場合は何も表示しない
        }

        // super_adminは全データ閲覧可能
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // 本部店舗のユーザーは全データ閲覧可能
        if ($user->store && $user->store->isHeadquarters()) {
            return $query;
        }

        // FC加盟店のユーザーは自店舗の発注のみ閲覧可能
        if ($user->store && $user->store->isFcStore()) {
            return $query->where('fc_store_id', $user->store_id);
        }

        // その他のユーザーは何も表示しない
        return $query->whereRaw('1 = 0');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('発注情報')
                    ->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->label('発注番号')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($record) => $record !== null),
                        Forms\Components\Select::make('headquarters_store_id')
                            ->label('発注先本部')
                            ->options(
                                Store::where('fc_type', 'headquarters')
                                    ->pluck('name', 'id')
                            )
                            ->required()
                            ->reactive()
                            ->searchable()
                            ->disabled(fn ($record) => $record !== null && !$record->isEditable()),
                        Forms\Components\Select::make('fc_store_id')
                            ->label('発注元FC店舗')
                            ->options(function (Forms\Get $get) {
                                $headquartersId = $get('headquarters_store_id');
                                if (!$headquartersId) {
                                    return Store::where('fc_type', 'fc_store')
                                        ->pluck('name', 'id');
                                }
                                return Store::where('fc_type', 'fc_store')
                                    ->where('headquarters_store_id', $headquartersId)
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->searchable()
                            ->disabled(fn ($record) => $record !== null && !$record->isEditable()),
                        Forms\Components\Select::make('status')
                            ->label('ステータス')
                            ->options([
                                'draft' => '下書き',
                                'ordered' => '発注済み',
                                'shipped' => '発送済み',
                                'delivered' => '納品完了',
                                'cancelled' => 'キャンセル',
                            ])
                            ->disabled()
                            ->visible(fn ($record) => $record !== null),
                        Forms\Components\Textarea::make('notes')
                            ->label('備考')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('発注明細')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('fc_product_id')
                                    ->label('商品')
                                    ->options(function (Forms\Get $get) {
                                        $headquartersId = $get('../../headquarters_store_id');
                                        if (!$headquartersId) {
                                            return [];
                                        }
                                        return FcProduct::where('headquarters_store_id', $headquartersId)
                                            ->where('is_active', true)
                                            ->get()
                                            ->mapWithKeys(fn ($product) => [
                                                $product->id => "{$product->sku} - {$product->name} (¥" . number_format($product->unit_price) . ")"
                                            ]);
                                    })
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $product = FcProduct::find($state);
                                            if ($product) {
                                                $set('unit_price', $product->unit_price);
                                                $set('tax_rate', $product->tax_rate);
                                                $set('product_name', $product->name);
                                                $set('product_sku', $product->sku);
                                            }
                                        }
                                    })
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('数量')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                        $unitPrice = floatval($get('unit_price') ?? 0);
                                        $taxRate = floatval($get('tax_rate') ?? 10);
                                        $quantity = intval($state ?? 1);
                                        $subtotal = $unitPrice * $quantity;
                                        $taxAmount = $subtotal * ($taxRate / 100);
                                        $set('subtotal', $subtotal);
                                        $set('tax_amount', $taxAmount);
                                        $set('total', $subtotal + $taxAmount);
                                    })
                                    ->columnSpan(1),
                                Forms\Components\Hidden::make('product_name'),
                                Forms\Components\Hidden::make('product_sku'),
                                Forms\Components\Hidden::make('unit_price'),
                                Forms\Components\Hidden::make('tax_rate'),
                                Forms\Components\Hidden::make('subtotal'),
                                Forms\Components\Hidden::make('tax_amount'),
                                Forms\Components\Hidden::make('total'),
                                Forms\Components\Placeholder::make('item_total_display')
                                    ->label('小計')
                                    ->content(function (Forms\Get $get) {
                                        $total = floatval($get('total') ?? 0);
                                        return '¥' . number_format($total);
                                    })
                                    ->columnSpan(2),
                            ])
                            ->columns(6)
                            ->defaultItems(1)
                            ->addActionLabel('商品を追加')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->disabled(fn ($record) => $record !== null && !$record->isEditable()),
                    ]),

                Forms\Components\Section::make('金額')
                    ->schema([
                        Forms\Components\Placeholder::make('subtotal_display')
                            ->label('小計（税抜）')
                            ->content(fn ($record) => $record ? '¥' . number_format($record->subtotal) : '¥0'),
                        Forms\Components\Placeholder::make('tax_amount_display')
                            ->label('消費税')
                            ->content(fn ($record) => $record ? '¥' . number_format($record->tax_amount) : '¥0'),
                        Forms\Components\Placeholder::make('total_amount_display')
                            ->label('合計（税込）')
                            ->content(fn ($record) => $record ? '¥' . number_format($record->total_amount) : '¥0'),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('発注番号')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fcStore.name')
                    ->label('FC店舗')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('headquartersStore.name')
                    ->label('本部')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('ステータス')
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'ordered',
                        'primary' => 'shipped',
                        'success' => 'delivered',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => '下書き',
                        'ordered' => '発注済み',
                        'shipped' => '発送済み',
                        'delivered' => '納品完了',
                        'cancelled' => 'キャンセル',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('明細数')
                    ->counts('items'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('合計金額')
                    ->money('jpy')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ordered_at')
                    ->label('発注日')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日')
                    ->dateTime('Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('ステータス')
                    ->options([
                        'draft' => '下書き',
                        'ordered' => '発注済み',
                        'shipped' => '発送済み',
                        'delivered' => '納品完了',
                        'cancelled' => 'キャンセル',
                    ]),
                Tables\Filters\SelectFilter::make('fc_store_id')
                    ->label('FC店舗')
                    ->options(
                        Store::where('fc_type', 'fc_store')
                            ->pluck('name', 'id')
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (FcOrder $record): bool => $record->isEditable()),
                Tables\Actions\Action::make('submit')
                    ->label('発注する')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn (FcOrder $record): bool => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->modalHeading('発注を確定しますか？')
                    ->modalDescription('この操作を行うと、本部に発注が送信されます。')
                    ->action(function (FcOrder $record) {
                        $record->update([
                            'status' => 'ordered',
                            'ordered_at' => now(),
                        ]);

                        // 本部に通知
                        try {
                            app(FcNotificationService::class)->notifyOrderSubmitted($record);
                        } catch (\Exception $e) {
                            \Log::error("FC発注通知エラー: " . $e->getMessage());
                        }

                        Notification::make()
                            ->title('発注を送信しました')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('ship')
                    ->label('発送')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->visible(fn (FcOrder $record): bool => $record->isShippable())
                    ->form([
                        Forms\Components\TextInput::make('shipping_tracking_number')
                            ->label('追跡番号')
                            ->maxLength(100),
                    ])
                    ->action(function (FcOrder $record, array $data) {
                        $record->update([
                            'status' => 'shipped',
                            'shipped_at' => now(),
                            'shipping_tracking_number' => $data['shipping_tracking_number'] ?? null,
                        ]);

                        // FC店舗に発送通知
                        try {
                            app(FcNotificationService::class)->notifyOrderShipped($record);
                        } catch (\Exception $e) {
                            \Log::error("FC発送通知エラー: " . $e->getMessage());
                        }

                        Notification::make()
                            ->title('発送処理を完了しました')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('deliver')
                    ->label('納品完了')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (FcOrder $record): bool => $record->isDeliverable())
                    ->requiresConfirmation()
                    ->action(function (FcOrder $record) {
                        $record->update([
                            'status' => 'delivered',
                            'delivered_at' => now(),
                        ]);
                        Notification::make()
                            ->title('納品を確認しました')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('cancel')
                    ->label('キャンセル')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (FcOrder $record): bool => $record->isCancellable())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('キャンセル理由')
                            ->required(),
                    ])
                    ->action(function (FcOrder $record, array $data) {
                        $record->update([
                            'status' => 'cancelled',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        Notification::make()
                            ->title('発注をキャンセルしました')
                            ->warning()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => false), // Disable bulk delete for safety
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

    public static function getPages(): array
    {
        return [
            'catalog' => Pages\FcCatalogPage::route('/catalog'),
            'index' => Pages\ListFcOrders::route('/'),
            'create' => Pages\CreateFcOrder::route('/create'),
            'view' => Pages\ViewFcOrder::route('/{record}'),
            'edit' => Pages\EditFcOrder::route('/{record}/edit'),
        ];
    }
}
