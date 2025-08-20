<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // スーパー管理者
        User::create([
            'name' => 'スーパー管理者',
            'email' => 'superadmin@xsyumeno.com',
            'password' => Hash::make('password'),
            'role' => 'superadmin',
            'is_active' => true,
        ]);

        // 管理者
        User::create([
            'name' => '管理者',
            'email' => 'admin@xsyumeno.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // マネージャー
        User::create([
            'name' => 'マネージャー',
            'email' => 'manager@xsyumeno.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'is_active' => true,
        ]);

        // スタッフ
        User::create([
            'name' => 'スタッフ',
            'email' => 'staff@xsyumeno.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'is_active' => true,
        ]);
    }
}