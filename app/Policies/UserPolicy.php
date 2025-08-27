<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        return $user->hasRole(['super_admin', 'owner', 'manager']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        // スーパーアドミンは全ユーザー閲覧可能
        if ($user->hasRole('super_admin')) {
            return true;
        }
        
        // オーナーは管理可能店舗のユーザーのみ
        if ($user->hasRole('owner')) {
            $manageableStoreIds = $user->manageableStores()->pluck('stores.id');
            return $manageableStoreIds->contains($model->store_id);
        }
        
        // 店長は同じ店舗のユーザーのみ閲覧可能
        if ($user->hasRole('manager')) {
            return $user->store_id === $model->store_id;
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
        
        return $user->hasRole(['super_admin', 'owner', 'manager']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        // スーパーアドミンは全ユーザー編集可能
        if ($user->hasRole('super_admin')) {
            return true;
        }
        
        // オーナーは管理可能店舗のユーザーのみ
        if ($user->hasRole('owner')) {
            $manageableStoreIds = $user->manageableStores()->pluck('stores.id');
            return $manageableStoreIds->contains($model->store_id);
        }
        
        // 店長は同じ店舗のユーザーのみ編集可能
        if ($user->hasRole('manager')) {
            return $user->store_id === $model->store_id;
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        // スーパーアドミン・オーナーのみユーザー削除可能
        if ($user->hasRole('super_admin')) {
            return true;
        }
        
        if ($user->hasRole('owner')) {
            $manageableStoreIds = $user->manageableStores()->pluck('stores.id');
            return $manageableStoreIds->contains($model->store_id);
        }
        
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }
}
