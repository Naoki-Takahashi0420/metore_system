<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        // 既存のロールと権限を削除
        \DB::table('model_has_permissions')->delete();
        \DB::table('model_has_roles')->delete();
        \DB::table('role_has_permissions')->delete();
        Permission::query()->delete();
        Role::query()->delete();

        // 権限を定義
        $permissions = [
            // ユーザー管理
            'view_all_users' => '全ユーザー閲覧',
            'view_store_users' => '店舗ユーザー閲覧',
            'create_users' => 'ユーザー作成',
            'edit_users' => 'ユーザー編集',
            'delete_users' => 'ユーザー削除',
            
            // 顧客管理
            'view_all_customers' => '全顧客閲覧',
            'view_store_customers' => '店舗顧客閲覧',
            'create_customers' => '顧客登録',
            'edit_customers' => '顧客編集',
            'delete_customers' => '顧客削除',
            'view_medical_records' => 'カルテ閲覧',
            'edit_medical_records' => 'カルテ編集',
            
            // 予約管理
            'view_all_reservations' => '全予約閲覧',
            'view_store_reservations' => '店舗予約閲覧',
            'create_reservations' => '予約作成',
            'edit_reservations' => '予約変更',
            'cancel_reservations' => '予約キャンセル',
            'block_time_slots' => '時間枠ブロック',
            
            // 店舗管理
            'view_all_stores' => '全店舗閲覧',
            'view_own_store' => '自店舗閲覧',
            'edit_all_stores' => '全店舗編集',
            'edit_own_store' => '自店舗編集',
            'manage_business_hours' => '営業時間管理',
            
            // シフト・勤怠管理
            'view_all_shifts' => '全シフト閲覧',
            'view_store_shifts' => '店舗シフト閲覧',
            'create_shifts' => 'シフト作成',
            'edit_all_shifts' => '全シフト編集',
            'edit_store_shifts' => '店舗シフト編集',
            'edit_own_shifts' => '自分のシフト編集',
            'view_all_time_records' => '全勤怠記録閲覧',
            'view_store_time_records' => '店舗勤怠記録閲覧',
            'edit_all_time_records' => '全勤怠記録編集',
            'edit_own_time_records' => '自分の勤怠記録編集',
            
            // メニュー管理
            'view_menus' => 'メニュー閲覧',
            'edit_menus' => 'メニュー編集',
            'manage_pricing' => '料金設定',
            
            // レポート・分析
            'view_all_reports' => '全レポート閲覧',
            'view_store_reports' => '店舗レポート閲覧',
            'export_data' => 'データエクスポート',
            'view_analytics' => '分析ダッシュボード',
            
            // システム管理
            'manage_system' => 'システム設定',
            'view_logs' => 'ログ閲覧',
            'backup_data' => 'バックアップ',
        ];

        // 権限を作成
        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(['name' => $name]);
        }

        // ロールの日本語名と説明を設定
        $roleDescriptions = [
            'super_admin' => [
                'display_name' => 'スーパーアドミン',
                'description' => 'システム全体の管理者。全店舗・全機能へのアクセス権限'
            ],
            'owner' => [
                'display_name' => 'オーナー',
                'description' => '複数店舗の所有者。指定された店舗の完全管理権限'
            ],
            'manager' => [
                'display_name' => '店長',
                'description' => '店舗の責任者。店舗運営に必要な管理権限'
            ],
            'staff' => [
                'display_name' => 'スタッフ',
                'description' => '一般従業員。基本業務と自分のシフト管理権限'
            ]
        ];

        // ロールと権限の割り当て
        $rolePermissions = [
            'super_admin' => [
                // スーパーアドミン：すべての権限
                'view_all_users', 'view_store_users', 'create_users', 'edit_users', 'delete_users',
                'view_all_customers', 'view_store_customers', 'create_customers', 'edit_customers', 'delete_customers',
                'view_medical_records', 'edit_medical_records',
                'view_all_reservations', 'view_store_reservations', 'create_reservations', 'edit_reservations', 'cancel_reservations', 'block_time_slots',
                'view_all_stores', 'view_own_store', 'edit_all_stores', 'edit_own_store', 'manage_business_hours',
                'view_all_shifts', 'view_store_shifts', 'create_shifts', 'edit_all_shifts', 'edit_store_shifts',
                'view_all_time_records', 'view_store_time_records', 'edit_all_time_records', 'edit_own_time_records',
                'view_menus', 'edit_menus', 'manage_pricing',
                'view_all_reports', 'view_store_reports', 'export_data', 'view_analytics',
                'manage_system', 'view_logs', 'backup_data',
            ],
            
            'owner' => [
                // オーナー：自分の店舗のみ全権限
                'view_store_users', 'create_users', 'edit_users',
                'view_store_customers', 'create_customers', 'edit_customers',
                'view_medical_records', 'edit_medical_records',
                'view_store_reservations', 'create_reservations', 'edit_reservations', 'cancel_reservations', 'block_time_slots',
                'view_own_store', 'edit_own_store', 'manage_business_hours',
                'view_store_shifts', 'create_shifts', 'edit_store_shifts',
                'view_store_time_records', 'edit_all_time_records',
                'view_menus', 'edit_menus', 'manage_pricing',
                'view_store_reports', 'export_data', 'view_analytics',
            ],
            
            'manager' => [
                // 店長：店舗運営に必要な権限
                'view_store_users', 'create_users', 'edit_users',
                'view_store_customers', 'create_customers', 'edit_customers',
                'view_medical_records', 'edit_medical_records',
                'view_store_reservations', 'create_reservations', 'edit_reservations', 'cancel_reservations', 'block_time_slots',
                'view_own_store', 'edit_own_store', 'manage_business_hours',
                'view_store_shifts', 'create_shifts', 'edit_store_shifts',
                'view_store_time_records', 'edit_all_time_records',
                'view_menus',
                'view_store_reports', 'export_data',
            ],
            
            'staff' => [
                // スタッフ：基本的な業務権限のみ
                'view_store_customers', 'create_customers',
                'view_medical_records',
                'view_store_reservations', 'create_reservations', 'edit_reservations',
                'view_own_store',
                'view_store_shifts', 'edit_own_shifts',
                'view_store_time_records', 'edit_own_time_records',
                'view_menus',
            ],
        ];

        // ロールを作成し、権限を割り当て
        foreach ($rolePermissions as $roleName => $permissions) {
            $roleInfo = $roleDescriptions[$roleName];
            
            $role = Role::firstOrCreate(
                ['name' => $roleName],
                [
                    'guard_name' => 'web',
                    'display_name' => $roleInfo['display_name'],
                    'description' => $roleInfo['description']
                ]
            );
            
            // ロールに表示名と説明を更新（既存ロールの場合）
            $role->update([
                'display_name' => $roleInfo['display_name'],
                'description' => $roleInfo['description']
            ]);
            
            $role->syncPermissions($permissions);
        }

        echo "✅ ロールと権限の設定が完了しました\n";
    }
}