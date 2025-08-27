<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StorePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        return $user->hasRole(['super_admin', 'owner', 'manager', 'staff']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Store $store): bool
    {
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        // スーパーアドミンは全店舗閲覧可能
        if ($user->hasRole('super_admin')) {
            return true;
        }
        
        // オーナーは管理可能店舗のみ
        if ($user->hasRole('owner')) {
            return $user->manageableStores()->where('stores.id', $store->id)->exists();
        }
        
        // 店長・スタッフは所属店舗のみ
        if ($user->hasRole(['manager', 'staff'])) {
            return $user->store_id === $store->id;
        }
        
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        // スーパーアドミンのみ店舗作成可能
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Store $store): bool
    {
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        // スーパーアドミンは全店舗編集可能
        if ($user->hasRole('super_admin')) {
            return true;
        }
        
        // オーナーは管理可能店舗のみ編集可能
        if ($user->hasRole('owner')) {
            return $user->manageableStores()->where('stores.id', $store->id)->exists();
        }
        
        // 店長は所属店舗のみ編集可能
        if ($user->hasRole('manager')) {
            return $user->store_id === $store->id;
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Store $store): bool
    {
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        // スーパーアドミンのみ店舗削除可能
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Store $store): bool
    {
        return $this->delete($user, $store);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Store $store): bool
    {
        return $this->delete($user, $store);
    }
}
