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

    protected function getHeaderActions(): array
    {
        $actions = [];
        
        // 管理者のみ店舗選択を表示
        if (auth()->user()->hasRole('super_admin')) {
            $stores = Store::orderBy('sort_order')->orderBy('name')->get();
            $storeCount = $stores->count();
            $currentStore = Store::find($this->selectedStore);
            
            // 店舗数に応じて表示方法を切り替え
            if ($storeCount <= 3) {
                // 3店舗以下：ボタン形式
                foreach ($stores as $store) {
                    $actions[] = Actions\Action::make('store_' . $store->id)
                        ->label($store->name)
                        ->size('sm')
                        ->color($this->selectedStore == $store->id ? 'primary' : 'gray')
                        ->action(function () use ($store) {
                            $this->selectedStore = $store->id;
                            $this->resetTable();
                        });
                }
            } elseif ($storeCount <= 8) {
                // 4-8店舗：ドロップダウン
                $storeActions = [];
                foreach ($stores as $store) {
                    if ($store->id != $this->selectedStore) {
                        $storeActions[] = Actions\Action::make('store_' . $store->id)
                            ->label($store->name)
                            ->icon($store->is_active ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
                            ->color($store->is_active ? 'success' : 'gray')
                            ->action(function () use ($store) {
                                $this->selectedStore = $store->id;
                                $this->resetTable();
                            });
                    }
                }
                
                if (!empty($storeActions)) {
                    $actions[] = Actions\ActionGroup::make($storeActions)
                        ->label($currentStore ? $currentStore->name : '店舗を選択')
                        ->icon('heroicon-o-building-storefront')
                        ->color('primary')
                        ->button()
                        ->size('sm');
                }
            } else {
                // 9店舗以上：検索可能なモーダル
                $actions[] = Actions\Action::make('select_store')
                    ->label($currentStore ? '店舗: ' . $currentStore->name : '店舗を選択')
                    ->icon('heroicon-o-building-storefront')
                    ->color('primary')
                    ->size('sm')
                    ->form([
                        \Filament\Forms\Components\Select::make('store_id')
                            ->label('店舗を選択')
                            ->options($stores->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->default($this->selectedStore)
                            ->helperText('店舗名で検索できます'),
                    ])
                    ->action(function (array $data) {
                        $this->selectedStore = $data['store_id'];
                        $this->resetTable();
                    });
            }
        }
        
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
