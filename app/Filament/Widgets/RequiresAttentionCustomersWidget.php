<?php

namespace App\Filament\Widgets;

use App\Models\CustomerSubscription;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class RequiresAttentionCustomersWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected int|string|array $columnSpan = 'full';
    
    protected static ?string $heading = '要対応顧客';
    
    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\BadgeColumn::make('attention_type')
                    ->label('種類')
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
                        return '❓ その他';
                    })
                    ->colors([
                        'danger' => '🔴 決済失敗',
                        'warning' => '⏸️ 休止中',
                        'info' => '⚠️ 終了間近',
                        'gray' => '❓ その他',
                    ]),
                    
                Tables\Columns\TextColumn::make('customer.last_name')
                    ->label('顧客名')
                    ->formatStateUsing(fn ($record) => 
                        $record->customer->last_name . ' ' . $record->customer->first_name
                    ),
                    
                Tables\Columns\TextColumn::make('store.name')
                    ->label('店舗'),
                    
                Tables\Columns\TextColumn::make('plan_name')
                    ->label('プラン'),
                    
                Tables\Columns\TextColumn::make('details')
                    ->label('詳細')
                    ->getStateUsing(function ($record) {
                        if ($record->payment_failed) {
                            $days = $record->payment_failed_at ? 
                                $record->payment_failed_at->diffInDays(now()) : 0;
                            $reason = $record->payment_failed_reason_display ?? '理由不明';
                            return "{$days}日前から失敗 - {$reason}";
                        }
                        
                        if ($record->is_paused) {
                            $endDate = $record->pause_end_date ? 
                                $record->pause_end_date->format('Y/m/d') : '未設定';
                            return "〜{$endDate}まで休止";
                        }
                        
                        if ($record->isEndingSoon()) {
                            $days = $record->end_date ? 
                                $record->end_date->diffInDays(now()) : 0;
                            return "あと{$days}日で終了";
                        }
                        
                        return '';
                    }),
                    
                Tables\Columns\TextColumn::make('payment_failed_notes')
                    ->label('メモ')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('view_subscription')
                    ->label('詳細')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => route('filament.admin.resources.customer-subscriptions.edit', $record))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false);
    }
    
    protected function getTableQuery(): Builder
    {
        $user = auth()->user();
        $query = CustomerSubscription::with(['customer', 'store'])
            ->where(function ($q) {
                $q->where('payment_failed', true)
                  ->orWhere('is_paused', true)
                  ->orWhere(function ($subQuery) {
                      $subQuery->whereNotNull('end_date')
                               ->whereDate('end_date', '<=', now()->addDays(30))
                               ->whereDate('end_date', '>', now());
                  });
            });
            
        // 権限に基づくフィルタリング
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }
        
        if ($user->hasRole('super_admin')) {
            return $query;
        }
        
        if ($user->hasRole('owner')) {
            $manageableStoreIds = $user->manageableStores()->pluck('stores.id');
            return $query->whereIn('store_id', $manageableStoreIds);
        }
        
        if ($user->hasRole(['manager', 'staff'])) {
            if ($user->store_id) {
                return $query->where('store_id', $user->store_id);
            }
            return $query->whereRaw('1 = 0');
        }
        
        return $query->whereRaw('1 = 0');
    }
    
    protected function getTableHeading(): string
    {
        $count = $this->getTableQuery()->count();
        return "要対応顧客 ({$count}件)";
    }
}