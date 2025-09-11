<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class TodayReservationsWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    
    protected int | string | array $columnSpan = 'full';
    
    public ?string $storeFilter = null;
    
    protected $listeners = ['store-changed' => 'updateStore'];
    
    public function mount(): void
    {
        // URLパラメータから店舗フィルターを取得
        $this->storeFilter = request()->get('storeFilter');
    }
    
    public function updateStore($storeId): void
    {
        $this->storeFilter = $storeId;
    }
    
    protected function getTableHeading(): string
    {
        $query = $this->getBaseQuery()
            ->whereDate('reservation_date', Carbon::today())
            ->whereNotIn('status', ['cancelled', 'canceled']);
            
        if ($this->storeFilter) {
            $query->where('store_id', $this->storeFilter);
        }
        
        $count = $query->count();
        $storeName = '';
        if ($this->storeFilter) {
            $store = \App\Models\Store::find($this->storeFilter);
            $storeName = $store ? " - {$store->name}" : '';
        }
        
        return "今日の予約 ({$count}件) - " . Carbon::today()->format('Y年n月j日') . $storeName;
    }
    
    protected function getBaseQuery(): Builder
    {
        $query = Reservation::query();
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
    
    public function table(Table $table): Table
    {
        $query = $this->getBaseQuery()
            ->with(['customer', 'store', 'menu', 'staff'])
            ->whereDate('reservation_date', Carbon::today())
            ->whereNotIn('status', ['cancelled', 'canceled']);
            
        if ($this->storeFilter) {
            $query->where('store_id', $this->storeFilter);
        }
        
        return $table
            ->query($query->orderBy('start_time', 'asc'))
            ->columns([
                Tables\Columns\TextColumn::make('start_time')
                    ->label('時間')
                    ->formatStateUsing(fn ($record) => 
                        Carbon::parse($record->start_time)->format('H:i') . '-' . 
                        Carbon::parse($record->end_time)->format('H:i')
                    )
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('store.name')
                    ->label('店舗')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('customer.full_name')
                    ->label('顧客名')
                    ->formatStateUsing(fn ($record) => 
                        $record->customer ? 
                        $record->customer->last_name . ' ' . $record->customer->first_name : 
                        '未設定'
                    )
                    ->searchable(['customer.last_name', 'customer.first_name']),
                    
                Tables\Columns\TextColumn::make('customer.phone')
                    ->label('電話番号')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('menu.name')
                    ->label('メニュー')
                    ->limit(20),
                    
                Tables\Columns\TextColumn::make('staff.name')
                    ->label('担当')
                    ->placeholder('未定'),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->label('状態')
                    ->colors([
                        'success' => 'completed',
                        'warning' => 'booked',
                        'info' => 'arrived',
                        'danger' => ['cancelled', 'no_show'],
                    ])
                    ->formatStateUsing(function ($state) {
                        return match($state) {
                            'booked' => '予約済',
                            'arrived' => '来店済',
                            'completed' => '完了',
                            'no_show' => '無断欠席',
                            'cancelled', 'canceled' => 'キャンセル',
                            default => $state,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('arrive')
                    ->label('来店')
                    ->icon('heroicon-m-user-plus')
                    ->color('info')
                    ->visible(fn ($record) => $record->status === 'booked')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['status' => 'arrived'])),
                    
                Tables\Actions\Action::make('complete')
                    ->label('完了')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => in_array($record->status, ['booked', 'arrived']))
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['status' => 'completed'])),
                    
                Tables\Actions\Action::make('create_medical_record')
                    ->label('カルテ作成')
                    ->icon('heroicon-m-document-plus')
                    ->color('primary')
                    ->url(fn ($record) => "/admin/medical-records/create?reservation_id={$record->id}")
                    ->visible(fn ($record) => 
                        in_array($record->status, ['arrived', 'completed']) &&
                        !$record->medicalRecords()->exists()
                    ),
                    
                Tables\Actions\EditAction::make()
                    ->label('編集')
                    ->icon('heroicon-m-pencil-square'),
            ])
            ->bulkActions([])
            ->paginated(false)
            ->defaultSort('start_time', 'asc');
    }
}