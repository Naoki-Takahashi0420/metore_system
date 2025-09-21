<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Reservation;
use App\Models\Store;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class IntegratedReservationManagement extends Page implements HasTable, HasForms
{
    use InteractsWithTable, InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = '予約管理（統合版）';
    protected static ?string $navigationGroup = '予約管理';
    protected static ?int $navigationSort = 99;

    public static function shouldRegisterNavigation(): bool
    {
        return false; // ナビゲーションから非表示
    }
    protected static string $view = 'filament.pages.integrated-reservation-management';

    protected ?string $heading = '予約管理';
    protected ?string $subheading = 'カレンダーから日付を選択して予約詳細を確認・管理できます';

    // 選択された日付と店舗
    public ?string $selectedDate = null;
    public ?int $selectedStoreId = null;

    public function mount(): void
    {
        // 初期値設定
        $this->selectedDate = now()->format('Y-m-d');

        $user = Auth::user();
        if ($user->hasRole('super_admin')) {
            $this->selectedStoreId = Store::first()?->id;
        } else {
            $this->selectedStoreId = $user->store_id;
        }
    }

    // カレンダーから日付が選択された時
    #[On('date-selected')]
    public function selectDate($date): void
    {
        $this->selectedDate = $date;
        $this->resetTable(); // テーブルをリセット
    }

    // 店舗が変更された時
    public function updatedSelectedStoreId(): void
    {
        $this->dispatch('store-changed', storeId: $this->selectedStoreId);
        $this->resetTable();
    }

    // テーブルの設定
    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('reservation_number')
                    ->label('予約番号')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('時間')
                    ->formatStateUsing(fn ($record) =>
                        Carbon::parse($record->start_time)->format('H:i') . ' - ' .
                        Carbon::parse($record->end_time)->format('H:i')
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.full_name')
                    ->label('顧客名')
                    ->formatStateUsing(fn ($record) =>
                        $record->customer ?
                        $record->customer->last_name . ' ' . $record->customer->first_name :
                        'ー'
                    )
                    ->searchable(['customers.last_name', 'customers.first_name']),

                Tables\Columns\TextColumn::make('customer.phone')
                    ->label('電話番号')
                    ->searchable(),

                Tables\Columns\TextColumn::make('menu.name')
                    ->label('メニュー')
                    ->wrap(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('ステータス')
                    ->colors([
                        'success' => 'booked',
                        'gray' => 'completed',
                        'danger' => 'cancelled',
                        'warning' => 'no_show',
                    ])
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'booked' => '予約確定',
                        'completed' => '完了',
                        'cancelled' => 'キャンセル',
                        'no_show' => '来店なし',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('金額')
                    ->money('JPY')
                    ->sortable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('備考')
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 30) {
                            return null;
                        }
                        return $state;
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('ステータス')
                    ->options([
                        'booked' => '予約確定',
                        'completed' => '完了',
                        'cancelled' => 'キャンセル',
                        'no_show' => '来店なし',
                    ])
                    ->default('booked'),
            ])
            ->actions([
                Tables\Actions\Action::make('complete')
                    ->label('完了')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'booked')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'completed']);
                        Notification::make()
                            ->title('予約を完了しました')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('キャンセル')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'booked')
                    ->requiresConfirmation()
                    ->modalHeading('予約をキャンセル')
                    ->modalDescription('この予約をキャンセルしてもよろしいですか？')
                    ->action(function ($record) {
                        $record->update(['status' => 'cancelled']);
                        Notification::make()
                            ->title('予約をキャンセルしました')
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\Action::make('no_show')
                    ->label('来店なし')
                    ->icon('heroicon-o-user-minus')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'booked')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'no_show']);

                        // 顧客のno_show_countを更新
                        if ($record->customer) {
                            $record->customer->increment('no_show_count');
                        }

                        Notification::make()
                            ->title('来店なしとして記録しました')
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make()
                    ->label('詳細'),

                Tables\Actions\EditAction::make()
                    ->label('編集'),
            ])
            ->defaultSort('start_time', 'asc')
            ->striped()
            ->emptyStateHeading('予約がありません')
            ->emptyStateDescription($this->selectedDate ?
                Carbon::parse($this->selectedDate)->format('Y年m月d日') . 'の予約はありません' :
                '日付を選択してください'
            );
    }

    // テーブルクエリの取得
    protected function getTableQuery()
    {
        $query = Reservation::query()->with(['customer', 'menu', 'store']);

        // 日付フィルタ
        if ($this->selectedDate) {
            $query->whereDate('reservation_date', $this->selectedDate);
        }

        // 店舗フィルタ
        if ($this->selectedStoreId) {
            $query->where('store_id', $this->selectedStoreId);
        }

        // 権限によるフィルタ
        $user = Auth::user();
        if (!$user->hasRole('super_admin')) {
            if ($user->hasRole('owner')) {
                $manageableStoreIds = $user->manageableStores()->pluck('stores.id');
                $query->whereIn('store_id', $manageableStoreIds);
            } else {
                $query->where('store_id', $user->store_id);
            }
        }

        return $query;
    }

    // カレンダーイベントの取得（ウィジェット用）
    public function getCalendarEvents($start, $end): array
    {
        $query = Reservation::query()
            ->whereBetween('reservation_date', [$start, $end]);

        if ($this->selectedStoreId) {
            $query->where('store_id', $this->selectedStoreId);
        }

        // 権限フィルタ
        $user = Auth::user();
        if (!$user->hasRole('super_admin')) {
            if ($user->hasRole('owner')) {
                $manageableStoreIds = $user->manageableStores()->pluck('stores.id');
                $query->whereIn('store_id', $manageableStoreIds);
            } else {
                $query->where('store_id', $user->store_id);
            }
        }

        $reservationsByDate = $query
            ->selectRaw('reservation_date, COUNT(*) as count,
                        SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_count')
            ->groupBy('reservation_date')
            ->get();

        return $reservationsByDate->map(function ($group) {
            $date = Carbon::parse($group->reservation_date);
            $activeCount = $group->count - $group->cancelled_count;

            // カラー設定
            $backgroundColor = '#f3f4f6';
            if ($activeCount == 0) {
                $backgroundColor = '#f3f4f6';
            } elseif ($activeCount <= 3) {
                $backgroundColor = '#86efac';
            } elseif ($activeCount <= 6) {
                $backgroundColor = '#fde047';
            } elseif ($activeCount <= 9) {
                $backgroundColor = '#fb923c';
            } else {
                $backgroundColor = '#dc2626';
            }

            return [
                'date' => $date->format('Y-m-d'),
                'count' => $activeCount,
                'cancelled' => $group->cancelled_count,
                'color' => $backgroundColor,
            ];
        })->toArray();
    }

    // 店舗リストの取得
    public function getStores(): array
    {
        $user = Auth::user();

        if ($user->hasRole('super_admin')) {
            return Store::where('is_active', true)->pluck('name', 'id')->toArray();
        } elseif ($user->hasRole('owner')) {
            return $user->manageableStores()->where('is_active', true)->pluck('name', 'stores.id')->toArray();
        } else {
            return $user->store ? [$user->store->id => $user->store->name] : [];
        }
    }
}