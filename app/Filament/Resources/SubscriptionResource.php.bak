<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\CustomerSubscription;
use App\Models\Customer;
use App\Models\SubscriptionPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionResource extends Resource
{
    protected static ?string $model = CustomerSubscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    
    protected static ?string $navigationLabel = 'サブスク会員';
    
    protected static ?string $modelLabel = 'サブスク契約';
    
    protected static ?string $pluralModelLabel = 'サブスク契約';
    
    protected static ?int $navigationSort = 3;
    
    protected static ?string $navigationGroup = 'メニュー管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('契約情報')
                    ->schema([
                        Forms\Components\Select::make('store_id')
                            ->label('店舗')
                            ->relationship('store', 'name')
                            ->searchable()
                            ->required()
                            ->reactive(),
                        
                        Forms\Components\Select::make('customer_id')
                            ->label('契約者')
                            ->options(function (Get $get) {
                                $storeId = $get('store_id');
                                $query = Customer::query();
                                if ($storeId) {
                                    $query->where('store_id', $storeId);
                                }
                                return $query->get()->mapWithKeys(function ($customer) {
                                    return [$customer->id => $customer->last_name . ' ' . $customer->first_name . ' (' . $customer->phone . ')'];
                                });
                            })
                            ->searchable()
                            ->required()
                            ->disabled(fn (Get $get) => !$get('store_id'))
                            ->helperText('店舗を選択してから顧客を選択してください'),
                        
                        Forms\Components\Select::make('menu_id')
                            ->label('サブスクメニュー')
                            ->options(function (Get $get) {
                                $storeId = $get('store_id');
                                $query = \App\Models\Menu::where('is_subscription', true);
                                if ($storeId) {
                                    $query->where('store_id', $storeId);
                                }
                                return $query->pluck('name', 'id');
                            })
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state) {
                                    $menu = \App\Models\Menu::find($state);
                                    if ($menu) {
                                        $set('monthly_price', $menu->subscription_monthly_price ?? $menu->price);
                                        $set('contract_months', $menu->default_contract_months ?? 1);
                                        $set('plan_name', $menu->name);
                                    }
                                }
                            }),
                        
                        Forms\Components\TextInput::make('monthly_price')
                            ->label('月額料金')
                            ->numeric()
                            ->prefix('¥')
                            ->required(),
                        
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('契約期間')
                    ->schema([
                        Forms\Components\DatePicker::make('billing_start_date')
                            ->label('課金開始日')
                            ->required()
                            ->default(now())
                            ->helperText('料金の請求を開始する日')
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state) {
                                    // 次回課金日を1ヶ月後に設定
                                    $nextBilling = \Carbon\Carbon::parse($state)->addMonth();
                                    $set('next_billing_date', $nextBilling->format('Y-m-d'));
                                }
                            }),
                        
                        Forms\Components\DatePicker::make('service_start_date')
                            ->label('サービス開始日')
                            ->required()
                            ->default(now())
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                if ($state && $get('contract_months')) {
                                    $endDate = \Carbon\Carbon::parse($state)
                                        ->addMonths($get('contract_months'))
                                        ->subDay(); // 最終日は-1日
                                    $set('end_date', $endDate->format('Y-m-d'));
                                }
                            })
                            ->helperText('実際にサービスが利用可能になる日'),
                        
                        Forms\Components\TextInput::make('contract_months')
                            ->label('契約期間')
                            ->numeric()
                            ->suffix('ヶ月')
                            ->default(1)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                if ($state && $get('service_start_date')) {
                                    $endDate = \Carbon\Carbon::parse($get('service_start_date'))
                                        ->addMonths($state)
                                        ->subDay();
                                    $set('end_date', $endDate->format('Y-m-d'));
                                }
                            }),
                        
                        Forms\Components\DatePicker::make('end_date')
                            ->label('終了日')
                            ->required()
                            ->reactive()
                            ->helperText('契約満了日（自動計算されますが編集可能）'),
                        
                        Forms\Components\DatePicker::make('next_billing_date')
                            ->label('次回請求日')
                            ->required()
                            ->default(fn() => now()->addMonth())
                            ->helperText('次回の料金請求日'),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('契約状態')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('状態')
                            ->options([
                                'trial' => 'お試し期間',
                                'active' => '有効',
                                'paused' => '一時停止',
                                'cancelled' => '解約済み',
                                'expired' => '期限切れ',
                            ])
                            ->required(),
                        
                        Forms\Components\Toggle::make('auto_renew')
                            ->label('自動更新'),
                        
                        Forms\Components\Select::make('payment_method')
                            ->label('支払い方法')
                            ->options([
                                'credit_card' => 'クレジットカード',
                                'bank_transfer' => '銀行振込',
                                'cash' => '現金',
                            ]),
                        
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('解約理由')
                            ->rows(3)
                            ->visible(fn ($get) => $get('status') === 'cancelled'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label('店舗')
                    ->sortable()
                    ->searchable()
                    ->default(fn ($record) => $record->customer?->store?->name ?? '-'),
                
                Tables\Columns\TextColumn::make('customer.full_name')
                    ->label('顧客名')
                    ->searchable(['customers.last_name', 'customers.first_name'])
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->customer ? 
                        $record->customer->last_name . ' ' . $record->customer->first_name : '-')
                    ->url(fn ($record) => $record->customer_id ? CustomerResource::getUrl('edit', ['record' => $record->customer_id]) : null),
                
                Tables\Columns\TextColumn::make('menu.name')
                    ->label('契約メニュー')
                    ->sortable()
                    ->default(fn ($record) => $record->plan_name ?? '-'),
                
                Tables\Columns\TextColumn::make('monthly_price')
                    ->label('月額料金')
                    ->money('JPY')
                    ->sortable()
                    ->default(fn ($record) => $record->monthly_price ?? $record->amount ?? 0),
                
                Tables\Columns\TextColumn::make('contract_months')
                    ->label('契約期間')
                    ->formatStateUsing(fn ($state) => $state ? "{$state}ヶ月" : '-')
                    ->default(fn ($record) => $record->contract_months ?? '-'),
                
                Tables\Columns\BadgeColumn::make('status')
                    ->label('状態')
                    ->colors([
                        'warning' => 'trial',
                        'success' => 'active',
                        'gray' => 'paused',
                        'danger' => ['cancelled', 'expired'],
                    ])
                    ->formatStateUsing(fn ($state) => match($state) {
                        'trial' => 'お試し期間',
                        'active' => '有効',
                        'paused' => '一時停止',
                        'cancelled' => '解約済み',
                        'expired' => '期限切れ',
                        default => $state,
                    }),
                
                Tables\Columns\TextColumn::make('billing_start_date')
                    ->label('課金開始日')
                    ->date('Y/m/d')
                    ->sortable()
                    ->default(fn ($record) => $record->billing_start_date ?? $record->billing_date ?? '-'),
                
                Tables\Columns\TextColumn::make('service_start_date')
                    ->label('サービス開始日')
                    ->date('Y/m/d')
                    ->sortable()
                    ->default(fn ($record) => $record->service_start_date ?? $record->start_date ?? '-'),
                
                Tables\Columns\TextColumn::make('end_date')
                    ->label('終了日')
                    ->date('Y/m/d')
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        // end_dateが設定されていればそれを使用
                        if ($record->end_date) {
                            return \Carbon\Carbon::parse($record->end_date)->format('Y/m/d');
                        }
                        // なければservice_start_date + contract_monthsで計算
                        $startDate = $record->service_start_date ?? $record->start_date;
                        if ($startDate && $record->contract_months) {
                            return \Carbon\Carbon::parse($startDate)
                                ->addMonths($record->contract_months)
                                ->subDay()
                                ->format('Y/m/d');
                        }
                        return '-';
                    }),
                
                Tables\Columns\TextColumn::make('next_billing_date')
                    ->label('次回請求日')
                    ->date('Y/m/d')
                    ->sortable(),
                
                Tables\Columns\IconColumn::make('auto_renew')
                    ->label('自動更新')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('店舗')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload(),
                
                Tables\Filters\SelectFilter::make('status')
                    ->label('状態')
                    ->options([
                        'trial' => 'お試し期間',
                        'active' => '有効',
                        'paused' => '一時停止',
                        'cancelled' => '解約済み',
                        'expired' => '期限切れ',
                    ])
                    ->default('active'),
                
                Tables\Filters\Filter::make('expiring_soon')
                    ->label('期限切れ間近')
                    ->query(fn ($query) => $query->whereDate('end_date', '<=', now()->addDays(30)))
                    ->toggle(),
                
                Tables\Filters\TernaryFilter::make('auto_renew')
                    ->label('自動更新'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('詳細'),
                Tables\Actions\Action::make('edit_customer')
                    ->label('顧客編集')
                    ->icon('heroicon-o-pencil')
                    ->url(fn ($record) => $record->customer_id ? CustomerResource::getUrl('edit', ['record' => $record->customer_id]) : null)
                    ->openUrlInNewTab(),
                
                Tables\Actions\Action::make('renew')
                    ->label('更新')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'active')
                    ->action(function ($record) {
                        app(\App\Services\SubscriptionService::class)->renew($record);
                    }),
                
                Tables\Actions\Action::make('pause')
                    ->label('一時停止')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'active')
                    ->action(fn ($record) => $record->update(['status' => 'paused'])),
                
                Tables\Actions\Action::make('resume')
                    ->label('再開')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'paused')
                    ->action(fn ($record) => $record->update(['status' => 'active'])),
                
                Tables\Actions\Action::make('cancel')
                    ->label('解約')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => in_array($record->status, ['active', 'trial']))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('解約理由')
                            ->required(),
                    ])
                    ->action(function ($record, $data) {
                        $record->update([
                            'status' => 'cancelled',
                            'cancellation_reason' => $data['cancellation_reason'],
                            'end_date' => now(),
                        ]);
                    }),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'view' => Pages\ViewSubscription::route('/{record}'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
    
    public static function getWidgets(): array
    {
        return [
            // \App\Filament\Widgets\SubscriptionStatsWidget::class, // 後で実装
        ];
    }
}