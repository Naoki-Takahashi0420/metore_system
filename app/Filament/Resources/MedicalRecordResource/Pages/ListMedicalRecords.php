<?php

namespace App\Filament\Resources\MedicalRecordResource\Pages;

use App\Filament\Resources\MedicalRecordResource;
use App\Models\Store;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Url;

class ListMedicalRecords extends ListRecords
{
    protected static string $resource = MedicalRecordResource::class;

    #[Url]
    public $storeFilter = null;
    
    public function mount(): void
    {
        parent::mount();
        
        $user = auth()->user();
        if ($user) {
            // スーパーアドミン以外はデフォルトで所属店舗を選択
            if (!$user->hasRole('super_admin') && !$this->storeFilter) {
                if ($user->hasRole('owner')) {
                    // オーナーの場合は管理可能店舗から最初の店舗を選択
                    $firstManageableStore = $user->manageableStores()->first();
                    $this->storeFilter = $firstManageableStore?->id;
                } else {
                    // その他の場合は所属店舗を選択
                    $this->storeFilter = $user->store_id;
                }
            }
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
        $storeOptions = collect();
        
        try {
            // スーパーアドミンは全店舗表示
            if ($user && $user->hasRole('super_admin')) {
                $storeOptions = Store::where('is_active', true)->whereNotNull('name')->pluck('name', 'id');
            }
            // オーナーは管理可能店舗のみ
            elseif ($user && $user->hasRole('owner')) {
                $storeOptions = $user->manageableStores()->whereNotNull('name')->pluck('name', 'id');
            }
            // その他は所属店舗のみ
            elseif ($user && $user->store) {
                $storeOptions = collect([$user->store_id => $user->store->name]);
            }
            
            // 店舗選択が必要な場合のみヘッダーを表示
            if ($storeOptions->count() > 1) {
                return view('filament.resources.medical-record-resource.pages.list-medical-records-header', [
                    'storeOptions' => $storeOptions->prepend('全店舗', '') ?? collect(),
                    'selectedStore' => $this->storeFilter ?? ''
                ]);
            }
        } catch (\Exception $e) {
            // エラーが発生した場合はヘッダーを表示しない
            \Log::error('MedicalRecordResource header error: ' . $e->getMessage());
        }
        
        return null;
    }
    
    public function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getTableQuery();
        $user = auth()->user();
        
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }
        
        // スーパーアドミンは全カルテ表示（店舗フィルターがある場合は適用）
        if ($user->hasRole('super_admin')) {
            if ($this->storeFilter) {
                $query->whereHas('customer', function ($q) {
                    $q->whereHas('reservations', function ($r) {
                        $r->where('store_id', $this->storeFilter);
                    });
                });
            }
            return $query;
        }
        
        // オーナーは管理可能店舗のカルテのみ表示
        if ($user->hasRole('owner')) {
            $manageableStoreIds = $user->manageableStores()->pluck('stores.id');
            
            if ($this->storeFilter) {
                // 特定店舗が選択されている場合
                if (in_array($this->storeFilter, $manageableStoreIds->toArray())) {
                    $query->whereHas('customer', function ($q) {
                        $q->whereHas('reservations', function ($r) {
                            $r->where('store_id', $this->storeFilter);
                        });
                    });
                } else {
                    // 管理権限がない店舗が選択されている場合は空を返す
                    return $query->whereRaw('1 = 0');
                }
            } else {
                // 全店舗の場合は管理可能店舗のカルテのみ
                $query->whereHas('customer', function ($q) use ($manageableStoreIds) {
                    $q->whereHas('reservations', function ($r) use ($manageableStoreIds) {
                        $r->whereIn('store_id', $manageableStoreIds);
                    });
                });
            }
            return $query;
        }
        
        // 店長・スタッフは所属店舗のカルテのみ表示
        if ($user->hasRole(['manager', 'staff'])) {
            $storeId = $this->storeFilter ?: $user->store_id;
            
            // 自分の所属店舗以外が選択されている場合は空を返す
            if ($this->storeFilter && $this->storeFilter != $user->store_id) {
                return $query->whereRaw('1 = 0');
            }
            
            if ($storeId) {
                $query->whereHas('customer', function ($q) use ($storeId) {
                    $q->whereHas('reservations', function ($r) use ($storeId) {
                        $r->where('store_id', $storeId);
                    });
                });
            } else {
                return $query->whereRaw('1 = 0');
            }
            return $query;
        }
        
        return $query->whereRaw('1 = 0');
    }
}
