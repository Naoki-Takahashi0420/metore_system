<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Store;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Url;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

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
            Actions\CreateAction::make(),
        ];
    }
    
    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        $user = auth()->user();
        
        if ($user && $user->hasRole('super_admin')) {
            $storeOptions = Store::where('is_active', true)->pluck('name', 'id');
            
            return view('filament.resources.user-resource.pages.list-users-header', [
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
}
