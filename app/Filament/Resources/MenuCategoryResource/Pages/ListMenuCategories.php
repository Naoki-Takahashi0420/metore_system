<?php

namespace App\Filament\Resources\MenuCategoryResource\Pages;

use App\Filament\Resources\MenuCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;

class ListMenuCategories extends ListRecords
{
    protected static string $resource = MenuCategoryResource::class;

    public ?string $selectedStore = null;

    public function mount(): void
    {
        parent::mount();
        
        // 管理者は最初の店舗を選択、それ以外は自分の店舗
        if (auth()->user()->hasRole('super_admin')) {
            $this->selectedStore = request()->query('store_id') ?? Store::first()?->id;
        } else {
            $this->selectedStore = auth()->user()->store_id;
        }
    }

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();
        
        if ($this->selectedStore) {
            $query->where('store_id', $this->selectedStore);
        }
        
        return $query;
    }

    protected function getHeaderActions(): array
    {
        $actions = [];
        
        // 管理者のみ店舗選択を表示
        if (auth()->user()->hasRole('super_admin')) {
            $stores = Store::all();
            foreach ($stores as $store) {
                $actions[] = Actions\Action::make('store_' . $store->id)
                    ->label($store->name)
                    ->color($this->selectedStore == $store->id ? 'primary' : 'gray')
                    ->action(function () use ($store) {
                        $this->selectedStore = $store->id;
                        $this->resetTable();
                    });
            }
        }
        
        $actions[] = Actions\CreateAction::make();
        
        return $actions;
    }
}
