<?php

namespace App\Filament\Resources\MenuResource\Pages;

use App\Filament\Resources\MenuResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;

class ListMenus extends ListRecords
{
    protected static string $resource = MenuResource::class;

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
            $query->whereHas('menuCategory', function ($q) {
                $q->where('store_id', $this->selectedStore);
            });
        }
        
        return $query;
    }

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        $user = auth()->user();
        
        if ($user && $user->hasRole('super_admin')) {
            $storeOptions = Store::where('is_active', true)->pluck('name', 'id');
            
            return view('filament.resources.menu-resource.pages.list-menus-header', [
                'storeOptions' => $storeOptions->prepend('全店舗', ''),
                'selectedStore' => $this->selectedStore ?? ''
            ]);
        }
        
        return null;
    }
    
    protected function getHeaderActions(): array
    {
        $actions = [];
        
        // カテゴリの存在をチェック
        $storeId = $this->selectedStore ?? auth()->user()->store_id;
        $hasCategories = \App\Models\MenuCategory::where('store_id', $storeId)
            ->where('is_active', true)
            ->exists();
        
        if ($hasCategories) {
            $actions[] = Actions\CreateAction::make();
        } else {
            $actions[] = Actions\Action::make('create_category_first')
                ->label('メニュー作成')
                ->icon('heroicon-o-plus')
                ->disabled()
                ->tooltip('まずカテゴリーを作成してください')
                ->color('gray');
        }
        
        return $actions;
    }
}
