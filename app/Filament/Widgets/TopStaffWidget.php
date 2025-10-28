<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Sale;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Illuminate\Database\Eloquent\Builder;

class TopStaffWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'スタッフ別売上（本日）';

    public ?int $selectedStoreId = null;

    public function mount(): void
    {
        $user = auth()->user();

        if ($user->hasRole('super_admin')) {
            $firstStore = \App\Models\Store::first();
            $this->selectedStoreId = $firstStore?->id;
        } else {
            $this->selectedStoreId = $user->store_id;
        }
    }

    #[On('store-changed')]
    public function updateStore($storeId): void
    {
        $this->selectedStoreId = $storeId;
    }

    public static function canView(): bool
    {
        $user = auth()->user();
        return $user && !$user->hasRole('staff');
    }

    public function table(Table $table): Table
    {
        $today = Carbon::today();

        return $table
            ->query(
                User::query()
                    ->whereHas('salesAsStaff', function (Builder $query) use ($today) {
                        $query->where('status', 'completed')
                            ->whereDate('sale_date', $today);

                        if ($this->selectedStoreId) {
                            $query->where('store_id', $this->selectedStoreId);
                        }
                    })
                    ->withCount([
                        'salesAsStaff as sales_count' => function (Builder $query) use ($today) {
                            $query->where('status', 'completed')
                                ->whereDate('sale_date', $today);

                            if ($this->selectedStoreId) {
                                $query->where('store_id', $this->selectedStoreId);
                            }
                        }
                    ])
                    ->withSum([
                        'salesAsStaff as total_sales' => function (Builder $query) use ($today) {
                            $query->where('status', 'completed')
                                ->whereDate('sale_date', $today);

                            if ($this->selectedStoreId) {
                                $query->where('store_id', $this->selectedStoreId);
                            }
                        }
                    ], 'total_amount')
                    ->orderByDesc('total_sales')
            )
            ->columns([
                Tables\Columns\TextColumn::make('rank')
                    ->label('順位')
                    ->state(function ($rowLoop) {
                        $rank = $rowLoop->index + 1;
                        return match ($rank) {
                            1 => '🥇 1位',
                            2 => '🥈 2位',
                            3 => '🥉 3位',
                            default => $rank . '位',
                        };
                    })
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('name')
                    ->label('スタッフ名')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sales_count')
                    ->label('施術件数')
                    ->suffix('件')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_sales')
                    ->label('売上金額')
                    ->money('JPY')
                    ->alignEnd()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('average_per_sale')
                    ->label('平均単価')
                    ->state(function (User $record): string {
                        $average = $record->sales_count > 0
                            ? round($record->total_sales / $record->sales_count)
                            : 0;
                        return '¥' . number_format($average);
                    })
                    ->alignEnd(),
            ])
            ->defaultSort('total_sales', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }
}
