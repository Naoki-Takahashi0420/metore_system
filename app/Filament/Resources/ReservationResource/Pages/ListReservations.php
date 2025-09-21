<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use App\Models\Store;
use App\Models\Reservation;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
use App\Filament\Widgets\TodayReservationsWidget;
use Carbon\Carbon;

class ListReservations extends ListRecords
{
    protected static string $resource = ReservationResource::class;

    protected static string $view = 'filament.resources.reservation-resource.pages.list-reservations';

    #[Url]
    public $storeFilter = null;

    public ?string $selectedDate = null;
    public bool $showCalendar = true;

    public function mount(): void
    {
        parent::mount();

        $this->selectedDate = now()->format('Y-m-d');

        $user = auth()->user();
        if ($user && !$user->hasRole('super_admin') && !$this->storeFilter) {
            $this->storeFilter = $user->store_id;
        }
    }

    #[On('date-selected')]
    public function selectDate($date): void
    {
        $this->selectedDate = $date;
        $this->resetTable();
    }

    public function toggleView(): void
    {
        $this->showCalendar = !$this->showCalendar;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('toggle_view')
                ->label(fn () => $this->showCalendar ? 'リスト表示' : 'カレンダー表示')
                ->icon(fn () => $this->showCalendar ? 'heroicon-o-list-bullet' : 'heroicon-o-calendar-days')
                ->color('gray')
                ->action('toggleView'),
            Actions\CreateAction::make()
                ->label('新規予約')
                ->icon('heroicon-o-plus-circle'),
            Actions\Action::make('quick_phone_reservation')
                ->label('電話予約を追加')
                ->icon('heroicon-o-phone')
                ->color('success')
                ->url(fn () => static::getResource()::getUrl('create') . '?source=phone')
                ->extraAttributes([
                    'title' => '電話で受けた予約を素早く登録'
                ]),
        ];
    }
    
    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        $user = auth()->user();
        
        if ($user && $user->hasRole('super_admin')) {
            $storeOptions = Store::where('is_active', true)->pluck('name', 'id');
            
            return view('filament.resources.reservation-resource.pages.list-reservations-header', [
                'storeOptions' => $storeOptions->prepend('全店舗', ''),
                'selectedStore' => $this->storeFilter ?? ''
            ]);
        }
        
        return null;
    }
    
    public function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getTableQuery();

        if ($this->storeFilter) {
            $query->where('store_id', $this->storeFilter);
        }

        // カレンダー選択日でフィルタ
        if ($this->showCalendar && $this->selectedDate) {
            $query->whereDate('reservation_date', $this->selectedDate);
        }

        return $query;
    }

    public function getCalendarEvents($start, $end): array
    {
        $query = Reservation::query()
            ->whereBetween('reservation_date', [$start, $end]);

        if ($this->storeFilter) {
            $query->where('store_id', $this->storeFilter);
        }

        // 権限フィルタ
        $user = auth()->user();
        if (!$user->hasRole('super_admin')) {
            if ($user->store_id) {
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
    
    protected function getHeaderWidgets(): array
    {
        return [
            TodayReservationsWidget::make([
                'storeFilter' => $this->storeFilter,
            ]),
        ];
    }
}