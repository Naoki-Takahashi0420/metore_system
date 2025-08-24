<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class TodayReservationsWidget extends BaseWidget
{
    protected static ?int $sort = 0; // 最上部に表示
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $heading = '本日の予約';
    
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Reservation::query()
                    ->with(['customer', 'store', 'menu'])
                    ->whereDate('reservation_date', today())
                    ->orderBy('start_time', 'asc')
            )
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
                Tables\Columns\TextColumn::make('store.name')
                    ->label('店舗'),
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