<?php

namespace App\Traits;

use App\Models\Store;
use App\Models\Shift;

trait HasShiftPermissions
{
    /**
     * シフト管理画面へのアクセス権限
     */
    public function canAccessShiftManagement(): bool
    {
        return in_array($this->role, ['superadmin', 'admin', 'manager', 'staff']);
    }
    
    /**
     * シフトの作成権限
     */
    public function canCreateShift(Store $store): bool
    {
        // superadmin/adminは全店舗OK
        if (in_array($this->role, ['superadmin', 'admin'])) {
            return true;
        }
        
        // managerは自店舗のみ
        if ($this->role === 'manager') {
            return $this->store_id === $store->id || 
                   $this->managedStores()->where('stores.id', $store->id)->exists();
        }
        
        // staffは自店舗の自分のシフトのみ
        if ($this->role === 'staff') {
            return $this->store_id === $store->id;
        }
        
        return false;
    }
    
    /**
     * シフトの編集権限
     */
    public function canEditShift(Shift $shift): bool
    {
        // superadmin/adminは全て編集可
        if (in_array($this->role, ['superadmin', 'admin'])) {
            return true;
        }
        
        // managerは自店舗のシフトのみ
        if ($this->role === 'manager') {
            return $this->store_id === $shift->store_id || 
                   $this->managedStores()->where('stores.id', $shift->store_id)->exists();
        }
        
        // staffは自店舗のシフトを編集可
        if ($this->role === 'staff') {
            return $this->store_id === $shift->store_id;
        }
        
        return false;
    }
    
    /**
     * シフトの削除権限
     */
    public function canDeleteShift(Shift $shift): bool
    {
        // superadmin/adminとmanagerのみ削除可
        if (in_array($this->role, ['superadmin', 'admin'])) {
            return true;
        }
        
        if ($this->role === 'manager') {
            return $this->store_id === $shift->store_id || 
                   $this->managedStores()->where('stores.id', $shift->store_id)->exists();
        }
        
        return false;
    }
    
    /**
     * 表示可能な店舗リスト
     */
    public function getAccessibleStores()
    {
        // superadmin/adminは全店舗
        if (in_array($this->role, ['superadmin', 'admin'])) {
            return Store::where('is_active', true);
        }
        
        // manager/staffは所属店舗のみ
        $storeIds = [$this->store_id];
        
        if ($this->role === 'manager') {
            $managedStoreIds = $this->managedStores()->pluck('stores.id')->toArray();
            $storeIds = array_merge($storeIds, $managedStoreIds);
        }
        
        return Store::whereIn('id', array_filter($storeIds))
                    ->where('is_active', true);
    }
    
    /**
     * 編集可能なスタッフリスト
     */
    public function getEditableStaff(Store $store)
    {
        // superadmin/admin/managerは店舗の全スタッフ
        if (in_array($this->role, ['superadmin', 'admin', 'manager'])) {
            return $store->users()->where('is_active_staff', true);
        }
        
        // staffは自分のみ
        if ($this->role === 'staff') {
            return $store->users()->where('users.id', $this->id);
        }
        
        return collect();
    }
}