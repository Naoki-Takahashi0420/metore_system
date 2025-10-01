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
    protected static ?int $sort = 20;

    // リアルタイム更新のためのポーリング間隔（30秒）
    protected static ?string $pollingInterval = '30s';
    
    protected int | string | array $columnSpan = 'full';
    
    public ?string $storeFilter = null;
    public ?string $selectedDate = null;

    protected $listeners = [
        'store-changed' => 'updateStore',
        'date-changed' => 'updateDate'
    ];
    
    public function mount(): void
    {
        // URLパラメータから店舗フィルターを取得
        $this->storeFilter = request()->get('storeFilter');
        // 本日の日付で初期化
        $this->selectedDate = Carbon::today()->format('Y-m-d');
    }
    
    public function updateStore($storeId): void
    {
        $this->storeFilter = $storeId;
        $this->resetTable();
    }

    public function updateDate($date): void
    {
        $this->selectedDate = $date;
        $this->resetTable();
    }
    
    protected function getTableHeading(): string
    {
        $date = $this->selectedDate ? Carbon::parse($this->selectedDate) : Carbon::today();

        $query = $this->getBaseQuery()
            ->whereDate('reservation_date', $date)
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

        $dateLabel = $date->isToday() ? '今日' : $date->format('n月j日');
        return "予約一覧 ({$count}件) - {$dateLabel} " . $date->format('(Y年n月j日)') . $storeName;
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
        $date = $this->selectedDate ? Carbon::parse($this->selectedDate) : Carbon::today();

        $query = $this->getBaseQuery()
            ->with(['customer', 'store', 'menu', 'staff'])
            ->whereDate('reservation_date', $date)
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
                    ->label('ステータス')
                    ->colors([
                        'primary' => 'booked',
                        'success' => 'completed',
                        'warning' => 'no_show',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'booked' => '予約済み',
                        'completed' => '完了',
                        'no_show' => '来店なし',
                        'cancelled' => 'キャンセル',
                        'pending' => '予約済み',  // 旧データ用
                        'confirmed' => '予約済み', // 旧データ用
                        'arrived' => '完了', // 旧データを完了扱い
                        default => $state,
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('詳細')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn ($record) => "/admin/reservations/{$record->id}"),

                Tables\Actions\Action::make('reschedule')
                    ->label('日程変更')
                    ->icon('heroicon-o-calendar')
                    ->color('info')
                    ->url(fn ($record) => route('admin.reservations.reschedule', $record))
                    ->openUrlInNewTab(false)
                    ->visible(fn ($record) => $record->status === 'booked'),

                Tables\Actions\Action::make('complete')
                    ->label('完了')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('予約を完了にする')
                    ->modalDescription('この予約を完了にしてもよろしいですか？')
                    ->modalSubmitActionLabel('完了にする')
                    ->visible(fn ($record) => $record->status === 'booked')
                    ->action(fn ($record) => $record->update(['status' => 'completed'])),

                Tables\Actions\Action::make('no_show')
                    ->label('来店なし')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('来店なしにする')
                    ->modalDescription('この予約を来店なしにしてもよろしいですか？')
                    ->modalSubmitActionLabel('来店なしにする')
                    ->visible(fn ($record) => $record->status === 'booked')
                    ->action(fn ($record) => $record->update(['status' => 'no_show'])),

                Tables\Actions\Action::make('cancel')
                    ->label('キャンセル')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('予約をキャンセル')
                    ->modalDescription('この予約をキャンセルしてもよろしいですか？')
                    ->modalSubmitActionLabel('キャンセルする')
                    ->visible(fn ($record) => $record->status === 'booked')
                    ->action(fn ($record) => $record->update(['status' => 'cancelled'])),

                Tables\Actions\DeleteAction::make()
                    ->label('削除')
                    ->requiresConfirmation()
                    ->modalHeading('予約を削除')
                    ->modalDescription('この予約を完全に削除してもよろしいですか？この操作は取り消せません。')
                    ->modalSubmitActionLabel('削除する')
                    ->successNotificationTitle('予約を削除しました')
                    ->visible(fn ($record) =>
                        // キャンセル済みまたは完了済みの予約のみ削除可能
                        in_array($record->status, ['cancelled', 'canceled', 'completed', 'no_show'])
                    ),

                Tables\Actions\Action::make('create_medical_record')
                    ->label('カルテ作成')
                    ->icon('heroicon-m-document-plus')
                    ->color('primary')
                    ->url(fn ($record) => "/admin/medical-records/create?reservation_id={$record->id}")
                    ->visible(fn ($record) =>
                        $record->status === 'completed' &&
                        !$record->medicalRecords()->exists()
                    ),
            ])
            ->bulkActions([])
            ->paginated(false)
            ->defaultSort('start_time', 'asc');
    }
}