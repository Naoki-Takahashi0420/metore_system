<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReservationPolicy
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
    public function view(User $user, Reservation $reservation): bool
    {
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        // スーパーアドミンは全予約閲覧可能
        if ($user->hasRole('super_admin')) {
            return true;
        }
        
        // オーナー・店長・スタッフは同じ店舗のみ
        if ($user->hasRole(['owner', 'manager', 'staff'])) {
            return $user->store_id === $reservation->store_id;
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
        
        return $user->hasRole(['super_admin', 'owner', 'manager', 'staff']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Reservation $reservation): bool
    {
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        // スーパーアドミンは全予約編集可能
        if ($user->hasRole('super_admin')) {
            return true;
        }
        
        // オーナー・店長・スタッフは同じ店舗のみ
        if ($user->hasRole(['owner', 'manager', 'staff'])) {
            return $user->store_id === $reservation->store_id;
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Reservation $reservation): bool
    {
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        // スーパーアドミン・オーナー・店長のみキャンセル可能
        if ($user->hasRole(['super_admin', 'owner', 'manager'])) {
            if ($user->hasRole('super_admin')) {
                return true;
            }
            return $user->store_id === $reservation->store_id;
        }
        
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Reservation $reservation): bool
    {
        return $this->delete($user, $reservation);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Reservation $reservation): bool
    {
        return $this->delete($user, $reservation);
    }
}
