<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use App\Models\Reservation;
use App\Models\Store;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class CalendarView extends Page
{
    protected static string $resource = ReservationResource::class;

    protected static string $view = 'filament.resources.reservation-resource.pages.calendar-view';

    protected static ?string $title = '予約カレンダー';

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    #[Url]
    public $storeFilter = null;

    public function mount(): void
    {
        parent::mount();

        $user = auth()->user();
        if ($user && !$user->hasRole('super_admin') && !$this->storeFilter) {
            $this->storeFilter = $user->store_id;
        }
    }

    public function getReservations()
    {
        $query = Reservation::with(['customer', 'menu', 'staff', 'store'])
            ->whereNotIn('status', ['cancelled', 'canceled']);

        // 店舗フィルターを適用
        if ($this->storeFilter) {
            $query->where('store_id', $this->storeFilter);
        }

        // 権限に基づくフィルタリング
        $user = auth()->user();
        if ($user && !$user->hasRole('super_admin')) {
            if ($user->hasRole('owner')) {
                $manageableStoreIds = $user->manageableStores()->pluck('stores.id');
                $query->whereIn('store_id', $manageableStoreIds);
            } elseif ($user->hasRole(['manager', 'staff'])) {
                if ($user->store_id) {
                    $query->where('store_id', $user->store_id);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        }

        return $query->get()
            ->map(function ($reservation) {
                return [
                    'id' => $reservation->id,
                    'title' => $reservation->customer->last_name . ' ' . $reservation->customer->first_name . ' - ' . $reservation->menu->name,
                    'start' => $reservation->reservation_date->format('Y-m-d') . 'T' . $reservation->start_time,
                    'end' => $reservation->reservation_date->format('Y-m-d') . 'T' . $reservation->end_time,
                    'color' => match($reservation->status) {
                        'booked' => '#3b82f6',
                        'completed' => '#10b981',
                        'no_show' => '#ef4444',
                        default => '#6b7280'
                    },
                    'url' => "/admin/reservations/{$reservation->id}/edit",
                    'extendedProps' => [
                        'customer' => $reservation->customer->last_name . ' ' . $reservation->customer->first_name,
                        'phone' => $reservation->customer->phone,
                        'menu' => $reservation->menu->name,
                        'staff' => $reservation->staff?->name ?? '未定',
                        'status' => $reservation->status,
                        'store' => $reservation->store->name,
                    ]
                ];
            });
    }

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        $user = auth()->user();

        if ($user && $user->hasRole('super_admin')) {
            $storeOptions = Store::where('is_active', true)->pluck('name', 'id');

            return view('filament.resources.reservation-resource.pages.calendar-view-header', [
                'storeOptions' => $storeOptions->prepend('全店舗', ''),
                'selectedStore' => $this->storeFilter ?? ''
            ]);
        }

        return null;
    }
}