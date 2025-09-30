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
use Filament\Forms\Components\Select;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;

class MenuManager extends Page implements HasForms, HasActions
{
    use InteractsWithForms, InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'メニュー統合管理';
    protected static ?string $title = 'メニュー統合管理';
    protected static ?string $navigationGroup = 'メニュー管理';
    protected static ?int $navigationSort = 0;
    protected static string $view = 'filament.pages.menu-manager';

    public $selectedStore = null;
    public $categories = [];
    public $menus = [];
    public $storeSearchForm = [];

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
            ->map(function ($category) {
                $categoryArray = $category->toArray();
                // menusのサブスク情報が確実に含まれるようにする
                $categoryArray['menus'] = $category->menus->map(function ($menu) {
                    return $menu->toArray();
                })->toArray();
                return $categoryArray;
            })
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
            return Store::orderBy('sort_order')->orderBy('name')->get();
        }
        
        return Store::where('id', auth()->user()->store_id)->get();
    }

    public function selectStoreFromModal($storeId): void
    {
        $this->selectedStore = $storeId;
        $this->loadData();
        $this->dispatch('close-modal', id: 'store-selector-modal');
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
            ->title('メニューの並び順を更新しました')
            ->success()
            ->duration(1000)
            ->send();
    }

    public function updateCategoryOrder($categoryIds): void
    {
        foreach ($categoryIds as $index => $categoryId) {
            MenuCategory::where('id', $categoryId)->update(['sort_order' => $index]);
        }
        
        $this->loadData();
        
        Notification::make()
            ->title('カテゴリーの並び順を更新しました')
            ->success()
            ->duration(1000)
            ->send();
    }

    public function duplicateCategoryToStore($categoryId, $targetStoreId): void
    {
        if (!auth()->user()->hasRole('super_admin')) {
            return;
        }

        try {
            \DB::transaction(function () use ($categoryId, $targetStoreId) {
                $category = MenuCategory::with('menus.options')->find($categoryId);
                
                if (!$category) {
                    throw new \Exception('カテゴリーが見つかりません');
                }
                
                // 同じ店舗に既に同名のカテゴリーが存在するかチェック
                $exists = MenuCategory::where('store_id', $targetStoreId)
                    ->where('name', $category->name)
                    ->exists();
                    
                if ($exists) {
                    throw new \Exception('同名のカテゴリーが既に存在します');
                }
                
                // カテゴリーを複製（必要なフィールドのみコピー）
                $newCategory = new MenuCategory();
                $newCategory->name = $category->name;
                $newCategory->slug = \Str::slug($category->name . '-' . uniqid());
                $newCategory->description = $category->description;
                $newCategory->image_path = $category->image_path;
                $newCategory->sort_order = $category->sort_order;
                $newCategory->is_active = $category->is_active;
                $newCategory->store_id = $targetStoreId;
                $newCategory->save();
                
                // メニューも複製
                foreach ($category->menus as $menu) {
                    $newMenu = $menu->replicate();
                    $newMenu->category_id = $newCategory->id;  // menu_category_idではなくcategory_id
                    $newMenu->store_id = $targetStoreId;
                    $newMenu->save();
                    
                    // メニューオプションも複製
                    if ($menu->options) {
                        foreach ($menu->options as $option) {
                            $newOption = $option->replicate();
                            $newOption->menu_id = $newMenu->id;
                            $newOption->save();
                        }
                    }
                }
            });
            
            Notification::make()
                ->title('複製完了')
                ->body('カテゴリーとメニューを複製しました')
                ->success()
                ->send();
                
            $this->loadData();
        } catch (\Exception $e) {
            Notification::make()
                ->title('複製失敗')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'store_manager', 'staff']);
    }
}