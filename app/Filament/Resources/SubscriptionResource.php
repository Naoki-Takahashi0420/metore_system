<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\CustomerSubscription;
use App\Models\Customer;
use App\Models\SubscriptionPlan;
use Filament\Forms;
use Filament\Forms\Form;
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
    
    protected static ?string $navigationGroup = 'メニュー';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('契約情報')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('契約者')
                            ->options(Customer::all()->mapWithKeys(function ($customer) {
                                return [$customer->id => $customer->last_name . ' ' . $customer->first_name . ' (' . $customer->phone . ')'];
                            }))
                            ->searchable()
                            ->required(),
                        
                        Forms\Components\Select::make('plan_id')
                            ->label('定額プラン')
                            ->options(SubscriptionPlan::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state) {
                                    $plan = SubscriptionPlan::find($state);
                                    if ($plan) {
                                        $set('amount', $plan->price);
                                        $set('billing_cycle', $plan->billing_cycle);
                                    }
                                }
                            }),
                        
                        Forms\Components\TextInput::make('amount')
                            ->label('月額料金')
                            ->numeric()
                            ->prefix('¥')
                            ->required()
                            ->disabled(),
                        
                        Forms\Components\Select::make('billing_cycle')
                            ->label('支払い周期')
                            ->options([
                                'monthly' => '毎月',
                                'quarterly' => '3ヶ月ごと',
                                'semi_annual' => '6ヶ月ごと',
                                'annual' => '年一括',
                            ])
                            ->required()
                            ->disabled(),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('契約期間')
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('開始日')
                            ->required()
                            ->default(now()),
                        
                        Forms\Components\DatePicker::make('end_date')
                            ->label('終了日')
                            ->after('start_date'),
                        
                        Forms\Components\DatePicker::make('next_billing_date')
                            ->label('次回請求日')
                            ->required(),
                        
                        Forms\Components\DatePicker::make('trial_ends_at')
                            ->label('トライアル終了日'),
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
                Tables\Columns\TextColumn::make('customer.full_name')
                    ->label('顧客名')
                    ->searchable(['customers.last_name', 'customers.first_name'])
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('plan.name')
                    ->label('プラン')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('amount')
                    ->label('金額')
                    ->money('JPY')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('billing_cycle')
                    ->label('支払い周期')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'monthly' => '毎月',
                        'quarterly' => '3ヶ月ごと',
                        'semi_annual' => '6ヶ月ごと',
                        'annual' => '年一括',
                        default => $state,
                    }),
                
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
                
                Tables\Columns\TextColumn::make('next_billing_date')
                    ->label('次回請求日')
                    ->date('Y/m/d')
                    ->sortable(),
                
                Tables\Columns\IconColumn::make('auto_renew')
                    ->label('自動更新')
                    ->boolean(),
                
                Tables\Columns\TextColumn::make('start_date')
                    ->label('開始日')
                    ->date('Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('end_date')
                    ->label('終了日')
                    ->date('Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('状態')
                    ->options([
                        'trial' => 'お試し期間',
                        'active' => '有効',
                        'paused' => '一時停止',
                        'cancelled' => '解約済み',
                        'expired' => '期限切れ',
                    ]),
                
                Tables\Filters\SelectFilter::make('plan_id')
                    ->label('定額プラン')
                    ->options(SubscriptionPlan::where('is_active', true)->pluck('name', 'id')),
                
                Tables\Filters\TernaryFilter::make('auto_renew')
                    ->label('自動更新'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('renew')
                    ->label('更新')
                    ->icon('heroicon-o-refresh')
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