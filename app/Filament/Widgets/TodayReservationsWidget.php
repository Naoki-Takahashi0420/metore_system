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
        $user = auth()->user();

        // 初期店舗を設定
        if ($user->hasRole('super_admin')) {
            $stores = \App\Models\Store::where('is_active', true)->get();
        } elseif ($user->hasRole('owner')) {
            $stores = $user->manageableStores()->where('is_active', true)->get();
        } else {
            $stores = $user->store ? collect([$user->store]) : collect();
        }

        $this->storeFilter = $stores->first()?->id;
        $this->selectedDate = Carbon::today()->format('Y-m-d');

        logger('📍 TodayReservationsWidget mounted', [
            'storeFilter' => $this->storeFilter,
            'selectedDate' => $this->selectedDate
        ]);
    }

    // テーブルクエリ前に必ず呼ばれる
    protected function getTableQuery(): Builder
    {
        // storeFilterがnullの場合、再初期化
        if (!$this->storeFilter) {
            $user = auth()->user();
            if ($user->hasRole('super_admin')) {
                $stores = \App\Models\Store::where('is_active', true)->get();
            } elseif ($user->hasRole('owner')) {
                $stores = $user->manageableStores()->where('is_active', true)->get();
            } else {
                $stores = $user->store ? collect([$user->store]) : collect();
            }
            $this->storeFilter = $stores->first()?->id;

            logger('⚠️ storeFilter was null, re-initialized', [
                'storeFilter' => $this->storeFilter
            ]);
        }

        $date = $this->selectedDate ?: Carbon::today()->format('Y-m-d');
        $date = Carbon::parse($date);

        $query = $this->getBaseQuery()
            ->with(['customer', 'store', 'menu', 'staff', 'medicalRecords'])
            ->whereDate('reservation_date', $date)
            ->whereNotIn('status', ['cancelled', 'canceled']);

        // 店舗フィルターを適用
        if ($this->storeFilter) {
            $query->where('store_id', $this->storeFilter);
            logger('📍 Table query with store filter', [
                'storeFilter' => $this->storeFilter,
                'date' => $date->format('Y-m-d')
            ]);
        } else {
            logger('⚠️ Table query WITHOUT store filter', [
                'storeFilter' => $this->storeFilter,
                'date' => $date->format('Y-m-d')
            ]);
        }

        return $query->orderBy('start_time', 'asc');
    }

    public function updateStore($storeId, $date = null): void
    {
        $this->storeFilter = $storeId;
        if ($date) {
            $this->selectedDate = $date;
        }
        $this->resetTable();

        logger('📍 Store updated', [
            'storeFilter' => $this->storeFilter,
            'selectedDate' => $this->selectedDate
        ]);
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
        // table()が呼ばれる時点で必ず初期化されているようにする
        if (!$this->storeFilter || !$this->selectedDate) {
            $user = auth()->user();

            if (!$this->storeFilter) {
                if ($user->hasRole('super_admin')) {
                    $stores = \App\Models\Store::where('is_active', true)->get();
                } elseif ($user->hasRole('owner')) {
                    $stores = $user->manageableStores()->where('is_active', true)->get();
                } else {
                    $stores = $user->store ? collect([$user->store]) : collect();
                }
                $this->storeFilter = $stores->first()?->id;

                logger('⚠️ storeFilter initialized in table() method', [
                    'storeFilter' => $this->storeFilter
                ]);
            }

            if (!$this->selectedDate) {
                $this->selectedDate = Carbon::today()->format('Y-m-d');
            }
        }

        return $table
            ->query($this->getTableQuery())
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
                    ->form([
                        \Filament\Forms\Components\Select::make('cancel_reason')
                            ->label('理由')
                            ->options(function () {
                                $reasons = config('customer_risk.cancel_reasons', []);
                                return collect($reasons)->mapWithKeys(function ($config, $key) {
                                    return [$key => $config['label']];
                                })->toArray();
                            })
                            ->required()
                            ->helperText('店舗都合・システム修正はカウント対象外'),
                    ])
                    ->modalHeading('来店なしにする')
                    ->modalDescription('来店なしの理由を選択してください')
                    ->modalSubmitActionLabel('来店なしにする')
                    ->visible(fn ($record) => $record->status === 'booked')
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => 'no_show',
                            'cancel_reason' => $data['cancel_reason'] ?? 'customer_request',
                        ]);
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('キャンセル')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->form([
                        \Filament\Forms\Components\Select::make('cancel_reason')
                            ->label('キャンセル理由')
                            ->options(function () {
                                $reasons = config('customer_risk.cancel_reasons', []);
                                return collect($reasons)->mapWithKeys(function ($config, $key) {
                                    return [$key => $config['label']];
                                })->toArray();
                            })
                            ->required()
                            ->helperText('店舗都合・システム修正はカウント対象外'),
                    ])
                    ->modalHeading('予約をキャンセル')
                    ->modalDescription('キャンセル理由を選択してください')
                    ->modalSubmitActionLabel('キャンセルする')
                    ->visible(fn ($record) => $record->status === 'booked')
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => 'cancelled',
                            'cancel_reason' => $data['cancel_reason'] ?? 'customer_request',
                            'cancelled_at' => now(),
                        ]);
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('削除')
                    ->requiresConfirmation()
                    ->modalHeading('予約を削除')
                    ->modalDescription('この予約を完全に削除してもよろしいですか？この操作は取り消せません。')
                    ->modalSubmitActionLabel('削除する')
                    ->successNotificationTitle('予約を削除しました'),

                Tables\Actions\Action::make('create_medical_record')
                    ->label(function ($record) {
                        $hasMedicalRecord = $record->medicalRecords->count() > 0;
                        return $hasMedicalRecord ? 'カルテ編集' : 'カルテ作成';
                    })
                    ->icon('heroicon-m-document-plus')
                    ->color(function ($record) {
                        $hasMedicalRecord = $record->medicalRecords->count() > 0;
                        return $hasMedicalRecord ? 'info' : 'success';
                    })
                    ->url(function ($record) {
                        $medicalRecord = $record->medicalRecords->first();
                        if ($medicalRecord) {
                            return "/admin/medical-records/{$medicalRecord->id}/edit";
                        }
                        return "/admin/medical-records/create?reservation_id={$record->id}&customer_id={$record->customer_id}";
                    })
                    ->visible(fn ($record) => $record->status === 'completed'),
            ])
            ->bulkActions([])
            ->paginated(false)
            ->defaultSort('start_time', 'asc');
    }
}