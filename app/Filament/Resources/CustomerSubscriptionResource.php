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
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'id')
                    ->required(),
                Forms\Components\Select::make('store_id')
                    ->relationship('store', 'name'),
                Forms\Components\TextInput::make('plan_type')
                    ->required(),
                Forms\Components\TextInput::make('plan_name')
                    ->required(),
                Forms\Components\TextInput::make('monthly_limit')
                    ->numeric(),
                Forms\Components\TextInput::make('monthly_price')
                    ->required()
                    ->numeric(),
                Forms\Components\DatePicker::make('start_date')
                    ->required(),
                Forms\Components\DatePicker::make('end_date'),
                Forms\Components\DatePicker::make('next_billing_date'),
                Forms\Components\TextInput::make('payment_method')
                    ->required(),
                Forms\Components\TextInput::make('payment_reference'),
                Forms\Components\TextInput::make('current_month_visits')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\DatePicker::make('last_visit_date'),
                Forms\Components\TextInput::make('reset_day')
                    ->required()
                    ->numeric()
                    ->default(1),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
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
                            return '🔴 決済失敗';
                        }
                        if ($record->is_paused) {
                            return '⏸️ 休止中';
                        }
                        if ($record->isEndingSoon()) {
                            return '⚠️ 終了間近';
                        }
                        return '🟢 正常';
                    })
                    ->colors([
                        'danger' => '🔴 決済失敗',
                        'warning' => '⏸️ 休止中',
                        'info' => '⚠️ 終了間近',
                        'success' => '🟢 正常',
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
