<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerSubscriptionResource\Pages;
use App\Filament\Resources\CustomerSubscriptionResource\RelationManagers;
use App\Models\CustomerSubscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerSubscriptionResource extends Resource
{
    protected static ?string $model = CustomerSubscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    
    protected static ?string $navigationLabel = 'サブスク契約管理';
    
    protected static ?string $modelLabel = 'サブスク契約';
    
    protected static ?string $pluralModelLabel = 'サブスク契約';
    
    protected static ?int $navigationSort = 4;
    
    protected static ?string $navigationGroup = '顧客管理';
    
    protected static ?string $slug = 'subscriptions';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('顧客')
                            ->relationship('customer', 'id', function ($query) {
                                return $query->orderBy('last_name');
                            })
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->last_name . ' ' . $record->first_name)
                            ->searchable(['last_name', 'first_name', 'phone'])
                            ->required(),
                        
                        Forms\Components\Select::make('store_id')
                            ->label('店舗')
                            ->relationship('store', 'name')
                            ->required(),
                        
                        Forms\Components\TextInput::make('plan_name')
                            ->label('プラン名')
                            ->required(),
                        
                        Forms\Components\Select::make('status')
                            ->label('ステータス')
                            ->options([
                                'active' => '有効',
                                'inactive' => '無効',
                                'cancelled' => 'キャンセル済み',
                            ])
                            ->required(),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('料金・利用制限')
                    ->schema([
                        Forms\Components\TextInput::make('monthly_price')
                            ->label('月額料金')
                            ->numeric()
                            ->prefix('¥')
                            ->disabled()
                            ->helperText('プランで決定される料金（変更不可）'),
                        
                        Forms\Components\TextInput::make('monthly_limit')
                            ->label('月間利用上限')
                            ->numeric()
                            ->suffix('回')
                            ->disabled()
                            ->helperText('プランで決定される上限（変更不可）'),
                        
                        Forms\Components\TextInput::make('current_month_visits')
                            ->label('今月の利用回数')
                            ->numeric()
                            ->suffix('回')
                            ->disabled()
                            ->helperText('システムが自動管理'),
                        
                        Forms\Components\TextInput::make('reset_day')
                            ->label('リセット日')
                            ->numeric()
                            ->suffix('日')
                            ->default(1)
                            ->disabled()
                            ->helperText('毎月1日に自動リセット'),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('契約期間')
                    ->schema([
                        Forms\Components\DatePicker::make('billing_start_date')
                            ->label('課金開始日')
                            ->displayFormat('Y年m月d日'),
                        
                        Forms\Components\DatePicker::make('service_start_date')
                            ->label('サービス開始日')
                            ->displayFormat('Y年m月d日'),
                        
                        Forms\Components\DatePicker::make('end_date')
                            ->label('契約終了日')
                            ->displayFormat('Y年m月d日')
                            ->helperText('空欄の場合は無期限'),
                        
                        Forms\Components\DatePicker::make('next_billing_date')
                            ->label('次回請求日')
                            ->displayFormat('Y年m月d日'),
                        
                        Forms\Components\DatePicker::make('last_visit_date')
                            ->label('最終利用日')
                            ->displayFormat('Y年m月d日')
                            ->disabled(),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('決済情報')
                    ->schema([
                        Forms\Components\Select::make('payment_method')
                            ->label('決済方法')
                            ->options([
                                'robopay' => 'ロボットペイメント',
                                'credit' => 'クレジットカード',
                                'bank' => '銀行振込',
                                'cash' => '現金',
                            ])
                            ->required(),
                        
                        Forms\Components\TextInput::make('payment_reference')
                            ->label('決済参照番号')
                            ->helperText('外部決済サービスの参照番号'),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('メモ')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('管理メモ')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.last_name')
                    ->label('顧客名')
                    ->formatStateUsing(fn ($record) => 
                        $record->customer->last_name . ' ' . $record->customer->first_name
                    )
                    ->searchable(['customer.last_name', 'customer.first_name']),
                    
                Tables\Columns\BadgeColumn::make('status_display')
                    ->label('ステータス')
                    ->getStateUsing(function ($record) {
                        if ($record->payment_failed) {
                            return '決済失敗';
                        }
                        if ($record->is_paused) {
                            return '休止中';
                        }
                        if ($record->isEndingSoon()) {
                            return '終了間近';
                        }
                        return '正常';
                    })
                    ->colors([
                        'danger' => '決済失敗',
                        'warning' => '休止中',
                        'info' => '終了間近',
                        'success' => '正常',
                    ]),
                    
                Tables\Columns\TextColumn::make('plan_name')
                    ->label('プラン')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('store.name')
                    ->label('店舗')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('monthly_limit')
                    ->label('月間制限')
                    ->formatStateUsing(fn ($state) => $state ? "{$state}回" : '無制限'),
                    
                Tables\Columns\TextColumn::make('current_month_visits')
                    ->label('今月利用')
                    ->formatStateUsing(fn ($record) => 
                        $record->monthly_limit ? 
                        "{$record->current_month_visits}/{$record->monthly_limit}" : 
                        $record->current_month_visits
                    ),
                    
                Tables\Columns\TextColumn::make('end_date')
                    ->label('契約終了日')
                    ->date()
                    ->sortable()
                    ->placeholder('未設定'),
                    
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('決済方法')
                    ->formatStateUsing(fn ($record) => $record->payment_method_display)
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_failed')
                    ->label('決済状況')
                    ->options([
                        1 => '決済失敗のみ',
                        0 => '正常のみ',
                    ]),
                    
                Tables\Filters\SelectFilter::make('is_paused')
                    ->label('休止状況')
                    ->options([
                        1 => '休止中のみ',
                        0 => '稼働中のみ',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle_payment_failed')
                    ->label(fn ($record) => $record->payment_failed ? '決済復旧' : '決済失敗')
                    ->icon(fn ($record) => $record->payment_failed ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                    ->color(fn ($record) => $record->payment_failed ? 'success' : 'danger')
                    ->form([
                        Forms\Components\Select::make('payment_failed_reason')
                            ->label('失敗理由')
                            ->options(\App\Models\CustomerSubscription::getPaymentFailedReasonOptions())
                            ->required()
                            ->visible(fn ($record) => !$record->payment_failed),
                        Forms\Components\Textarea::make('payment_failed_notes')
                            ->label('メモ')
                            ->placeholder('決済状況の詳細や対応内容を記録')
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        if ($record->payment_failed) {
                            // 決済復旧
                            $record->update([
                                'payment_failed' => false,
                                'payment_failed_at' => null,
                                'payment_failed_reason' => null,
                                'payment_failed_notes' => $data['payment_failed_notes'] ?? null,
                            ]);
                        } else {
                            // 決済失敗に設定
                            $record->update([
                                'payment_failed' => true,
                                'payment_failed_at' => now(),
                                'payment_failed_reason' => $data['payment_failed_reason'],
                                'payment_failed_notes' => $data['payment_failed_notes'] ?? null,
                            ]);
                        }
                    }),
                    
                Tables\Actions\Action::make('pause')
                    ->label('休止')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(fn ($record) => !$record->is_paused)
                    ->requiresConfirmation()
                    ->modalHeading('サブスク休止の確認')
                    ->modalDescription(fn ($record) => 
                        "6ヶ月間休止します。{$record->customer->last_name} {$record->customer->first_name}様の将来の予約は自動キャンセルされます。"
                    )
                    ->action(function ($record) {
                        $record->pause(auth()->id(), '管理画面から手動休止');
                        
                        \Filament\Notifications\Notification::make()
                            ->title('休止設定完了')
                            ->body("6ヶ月間休止しました。{$record->pause_end_date->format('Y年m月d日')}に自動再開されます。")
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\Action::make('resume')
                    ->label('休止解除')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn ($record) => $record->is_paused)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->resume('manual');
                        
                        \Filament\Notifications\Notification::make()
                            ->title('休止解除完了')
                            ->body('サブスクが再開されました。')
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\EditAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerSubscriptions::route('/'),
            'create' => Pages\CreateCustomerSubscription::route('/create'),
            'edit' => Pages\EditCustomerSubscription::route('/{record}/edit'),
        ];
    }
}
