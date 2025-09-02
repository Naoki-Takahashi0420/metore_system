<?php

namespace App\Filament\Pages;

use App\Models\MenuCategory;
use App\Models\Menu;
use App\Models\Store;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Collection;
use Filament\Notifications\Notification;

class MenuManager extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'メニュー統合管理';
    protected static ?string $title = 'メニュー統合管理';
    protected static ?string $navigationGroup = 'メニュー管理';
    protected static ?int $navigationSort = 0;
    protected static string $view = 'filament.pages.menu-manager';

    public $selectedStore = null;
    public $categories = [];
    public $menus = [];

    public function mount(): void
    {
        if (!auth()->user()->hasRole('super_admin') && auth()->user()->store_id) {
            $this->selectedStore = auth()->user()->store_id;
        } else {
            $this->selectedStore = Store::first()?->id;
        }
        
        $this->loadData();
    }

    public function loadData(): void
    {
        if (!$this->selectedStore) {
            return;
        }

        $this->categories = MenuCategory::where('store_id', $this->selectedStore)
            ->with(['menus' => function ($query) {
                $query->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    public function selectStore($storeId): void
    {
        $this->selectedStore = $storeId;
        $this->loadData();
    }

    public function getStores(): Collection
    {
        if (auth()->user()->hasRole('super_admin')) {
            return Store::all();
        }
        
        return Store::where('id', auth()->user()->store_id)->get();
    }

    public function addCategory(): void
    {
        Notification::make()
            ->title('カテゴリー追加')
            ->body('右側のフォームから追加してください')
            ->info()
            ->send();
    }

    public function quickToggleMenu($menuId, $field): void
    {
        $menu = Menu::find($menuId);
        if ($menu) {
            $menu->$field = !$menu->$field;
            $menu->save();
            
            $this->loadData();
            
            Notification::make()
                ->title('更新完了')
                ->success()
                ->duration(1000)
                ->send();
        }
    }

    public function updateMenuOrder($categoryId, $menuIds): void
    {
        foreach ($menuIds as $index => $menuId) {
            Menu::where('id', $menuId)->update(['sort_order' => $index]);
        }
        
        $this->loadData();
        
        Notification::make()
            ->title('並び順を更新しました')
            ->success()
            ->duration(1000)
            ->send();
    }

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'store_manager', 'staff']);
    }
}