<?php

namespace App\Traits;

trait HasRoles
{
    /**
     * ユーザーが指定されたロールを持っているか確認
     */
    public function hasRole($roles): bool
    {
        if (is_string($roles)) {
            return $this->role === $roles;
        }
        
        if (is_array($roles)) {
            return in_array($this->role, $roles);
        }
        
        return false;
    }
    
    /**
     * ユーザーが指定された権限を持っているか確認
     */
    public function hasPermission($permission): bool
    {
        // スーパー管理者は全権限を持つ
        if ($this->role === 'super_admin') {
            return true;
        }
        
        $permissions = $this->permissions ?? [];
        
        return in_array($permission, $permissions);
    }
    
    /**
     * ユーザーが管理者権限を持っているか
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin']);
    }
    
    /**
     * ユーザーがマネージャー以上の権限を持っているか
     */
    public function isManager(): bool
    {
        return in_array($this->role, ['super_admin', 'admin', 'manager']);
    }
    
    /**
     * ロールの日本語表示名を取得
     */
    public function getRoleDisplayNameAttribute(): string
    {
        $roleNames = [
            'super_admin' => 'システム管理者',
            'admin' => '管理者',
            'manager' => 'マネージャー',
            'staff' => 'スタッフ',
            'viewer' => '閲覧者',
        ];
        
        return $roleNames[$this->role] ?? $this->role;
    }
    
    /**
     * 利用可能なロール一覧
     */
    public static function availableRoles(): array
    {
        return [
            'super_admin' => 'システム管理者',
            'admin' => '管理者',
            'manager' => 'マネージャー',
            'staff' => 'スタッフ',
            'viewer' => '閲覧者',
        ];
    }
    
    /**
     * 利用可能な権限一覧
     */
    public static function availablePermissions(): array
    {
        return [
            // 予約管理
            'reservations.view' => '予約閲覧',
            'reservations.create' => '予約作成',
            'reservations.edit' => '予約編集',
            'reservations.delete' => '予約削除',
            
            // 顧客管理
            'customers.view' => '顧客閲覧',
            'customers.create' => '顧客作成',
            'customers.edit' => '顧客編集',
            'customers.delete' => '顧客削除',
            
            // メニュー管理
            'menus.view' => 'メニュー閲覧',
            'menus.create' => 'メニュー作成',
            'menus.edit' => 'メニュー編集',
            'menus.delete' => 'メニュー削除',
            
            // 売上管理
            'sales.view' => '売上閲覧',
            'sales.create' => '売上記録',
            'sales.edit' => '売上編集',
            'sales.delete' => '売上削除',
            
            // スタッフ管理
            'staff.view' => 'スタッフ閲覧',
            'staff.create' => 'スタッフ作成',
            'staff.edit' => 'スタッフ編集',
            'staff.delete' => 'スタッフ削除',
            
            // 設定管理
            'settings.view' => '設定閲覧',
            'settings.edit' => '設定編集',
            
            // レポート
            'reports.view' => 'レポート閲覧',
            'reports.export' => 'レポート出力',
        ];
    }
}