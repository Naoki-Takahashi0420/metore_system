<?php

namespace App\Filament\Resources\CustomerSubscriptionResource\Pages;

use App\Filament\Resources\CustomerSubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCustomerSubscriptions extends ListRecords
{
    protected static string $resource = CustomerSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();
        $user = auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // スーパーアドミンは全て表示
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // オーナーは管理可能な店舗のサブスクのみ
        if ($user->hasRole('owner')) {
            $manageableStoreIds = $user->manageableStores()->pluck('stores.id')->toArray();
            return $query->whereIn('store_id', $manageableStoreIds);
        }

        // 店長・スタッフは所属店舗のサブスクのみ
        if ($user->hasRole(['manager', 'staff']) && $user->store_id) {
            return $query->where('store_id', $user->store_id);
        }

        return $query->whereRaw('1 = 0');
    }
}
