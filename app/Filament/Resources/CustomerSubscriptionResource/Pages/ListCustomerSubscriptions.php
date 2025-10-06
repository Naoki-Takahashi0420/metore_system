<?php

namespace App\Filament\Resources\CustomerSubscriptionResource\Pages;

use App\Filament\Resources\CustomerSubscriptionResource;
use App\Models\Store;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCustomerSubscriptions extends ListRecords
{
    protected static string $resource = CustomerSubscriptionResource::class;

    public $selectedStore = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.resources.header-with-store-selector', [
            'stores' => $this->getAvailableStores(),
            'actions' => $this->getCachedHeaderActions(),
        ]);
    }

    protected function getAvailableStores()
    {
        $user = auth()->user();

        if ($user->hasRole('super_admin')) {
            return Store::where('is_active', true)->orderBy('name')->get();
        } elseif ($user->hasRole('owner')) {
            return $user->manageableStores()->where('is_active', true)->orderBy('name')->get();
        } else {
            return $user->store ? collect([$user->store]) : collect();
        }
    }

    public function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        // 店舗選択によるフィルタリング
        if ($this->selectedStore) {
            $query->whereHas('menu', function($q) {
                $q->where('store_id', $this->selectedStore);
            });
        }

        return $query;
    }
}
