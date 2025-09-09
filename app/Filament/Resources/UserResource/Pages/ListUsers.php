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
        
        $user = \Filament\Facades\Filament::auth()->user();
        if ($user && !$user->hasRole('super_admin') && !$this->storeFilter) {
            $this->storeFilter = $user->store_id;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('新規作成'),
        ];
    }
    
    protected function getTableFiltersLayout(): ?string
    {
        return \Filament\Tables\Enums\FiltersLayout::AboveContent;
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
