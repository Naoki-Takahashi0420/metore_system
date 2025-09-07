<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use App\Models\Store;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class TodayReservationsWidget extends BaseWidget
{
    protected static ?int $sort = 2; // タイムラインの下に表示
    
    protected int | string | array $columnSpan = 'full';
    
    public $selectedStore = null;
    public $selectedDate = null;
    
    public function mount(): void
    {
        $firstStore = Store::where('is_active', true)->first();
        $this->selectedStore = $firstStore?->id;
        $this->selectedDate = Carbon::today()->format('Y-m-d');
    }
    
    #[On('store-changed')]
    public function updateStore($storeId, $date): void
    {
        $this->selectedStore = $storeId;
        $this->selectedDate = $date;
        $this->resetTable();
    }
    
    public function getHeading(): ?string
    {
        $date = Carbon::parse($this->selectedDate ?? today());
        $dateStr = $date->format('n月j日');
        $storeName = $this->selectedStore ? Store::find($this->selectedStore)?->name : '全店舗';
        return "{$dateStr}の予約 - {$storeName}";
    }
    
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Reservation::query()
                    ->with(['customer', 'store', 'menu'])
                    ->when($this->selectedStore, fn($query) => 
                        $query->where('store_id', $this->selectedStore)
                    )
                    ->whereDate('reservation_date', $this->selectedDate ?? today())
                    ->orderBy('start_time', 'asc')
            )
            ->emptyStateHeading('予約がありません')
            ->emptyStateDescription('この日の予約はまだ登録されていません')
            ->emptyStateIcon('heroicon-o-calendar')
            ->columns([
                Tables\Columns\TextColumn::make('start_time')
                    ->label('時間')
                    ->time('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.full_name')
                    ->label('顧客名')
                    ->getStateUsing(fn ($record) => 
                        $record->customer ? "{$record->customer->last_name} {$record->customer->first_name}" : '-'
                    )
                    ->searchable(),
                Tables\Columns\TextColumn::make('menu.name')
                    ->label('メニュー')
                    ->default('-'),
                Tables\Columns\TextColumn::make('seat_display')
                    ->label('配置')
                    ->getStateUsing(function ($record) {
                        if ($record->is_sub) {
                            return 'サブ枠';
                        } elseif ($record->seat_number) {
                            return '席' . $record->seat_number;
                        }
                        return '-';
                    })
                    ->badge()
                    ->color(fn ($state) => 
                        $state === 'サブ枠' ? 'warning' : 'primary'
                    ),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('ステータス')
                    ->getStateUsing(function ($record) {
                        // 24時間以内の新規予約
                        $isNew = Carbon::parse($record->created_at)->diffInHours(now()) <= 24;
                        
                        $statusLabel = match($record->status) {
                            'booked' => '予約済み',
                            'visited' => '来店済み',
                            'cancelled' => 'キャンセル',
                            default => $record->status,
                        };
                        
                        return $isNew && $record->status === 'booked' ? '🆕 ' . $statusLabel : $statusLabel;
                    })
                    ->colors([
                        'primary' => fn ($state) => str_contains($state, '🆕'),
                        'success' => fn ($state) => str_contains($state, '来店済み'),
                        'danger' => fn ($state) => str_contains($state, 'キャンセル'),
                        'warning' => fn ($state) => str_contains($state, '予約済み') && !str_contains($state, '🆕'),
                    ]),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('金額')
                    ->money('JPY')
                    ->sortable(),
            ])
            ->defaultSort('start_time', 'asc')
            ->paginated([5, 10, 25])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('詳細')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => "/admin/reservations/{$record->id}/edit"),
            ])
            ->headerActions([
                Tables\Actions\Action::make('view_all')
                    ->label('すべての予約を見る')
                    ->icon('heroicon-o-arrow-right')
                    ->color('primary')
                    ->url('/admin/reservations'),
            ]);
    }
    
    public function getTableRecordsPerPageSelectOptions(): array
    {
        return [5, 10, 25];
    }
}