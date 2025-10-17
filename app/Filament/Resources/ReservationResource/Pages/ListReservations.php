<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use App\Models\Store;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Url;

class ListReservations extends ListRecords
{
    protected static string $resource = ReservationResource::class;

    // カスタムビューを一時的に無効化して標準的なFilamentテーブルを使用
    // protected static string $view = 'filament.resources.reservation-resource.pages.list-reservations';

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

    protected function getHeaderActions(): array
    {
        return [
            // 予約作成はダッシュボードから行うため、ここでは不要
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

        return $query;
    }
    
    protected function getHeaderWidgets(): array
    {
        return [
            // \App\Filament\Widgets\TodayReservationTimelineWidget::class,
        ];
    }
}