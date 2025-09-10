<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Resources\SubscriptionResource;
use App\Filament\Widgets\SubscriptionStatsWidget;
use App\Models\Store;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Url;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

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
            // 新規作成ボタンを削除（顧客編集画面から作成）
        ];
    }
    
    protected function getHeaderWidgets(): array
    {
        return [
            SubscriptionStatsWidget::class,
        ];
    }
    
    public function getTitle(): string 
    {
        return 'サブスク契約管理';
    }
    
    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        $user = auth()->user();
        
        if ($user && $user->hasRole('super_admin')) {
            $storeOptions = Store::where('is_active', true)->pluck('name', 'id');
            
            return view('filament.resources.subscription-resource.pages.list-subscriptions-header', [
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
            $query->whereHas('customer', function ($q) {
                $q->whereHas('reservations', function ($r) {
                    $r->where('store_id', $this->storeFilter);
                });
            });
        }
        
        return $query;
    }
}