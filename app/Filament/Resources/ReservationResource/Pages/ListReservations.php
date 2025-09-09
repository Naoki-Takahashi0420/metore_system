<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use App\Models\Store;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Url;
use App\Filament\Widgets\TodayReservationsWidget;

class ListReservations extends ListRecords
{
    protected static string $resource = ReservationResource::class;

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
        
        return $query;
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