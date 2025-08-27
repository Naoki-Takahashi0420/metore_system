#!/bin/bash

echo "=== 本番環境の権限設定スクリプト ==="

# EC2接続情報
EC2_HOST="54.64.54.226"
EC2_USER="ubuntu"
KEY_PATH="$HOME/.ssh/xsyumeno-20250826-095948.pem"

# SSHコマンドを実行
ssh -o StrictHostKeyChecking=no -i "$KEY_PATH" ${EC2_USER}@${EC2_HOST} << 'ENDSSH'
set -e

echo "=== 本番環境での権限設定開始 ==="

cd /var/www/html

# 1. 権限シーダーを実行
echo "1. 権限シーダーを実行中..."
sudo php artisan db:seed --class=RolesAndPermissionsSeeder --force

# 2. PHPスクリプトで全管理者をスーパーアドミンに設定
echo "2. 全管理者にsuper_adminロールを設定中..."
sudo php artisan tinker << 'ENDPHP'
use App\Models\User;
use Spatie\Permission\Models\Role;

// super_adminロールが存在するか確認
$superAdminRole = Role::where('name', 'super_admin')->first();
if (!$superAdminRole) {
    echo "Error: super_admin ロールが見つかりません\n";
    exit(1);
}

// 管理者メールアドレスのリスト
$adminEmails = [
    'admin@eye-training.com',
    'naoki@yumeno-marketing.jp', 
    'superadmin@eye-training.com'
];

// 各管理者にsuper_adminロールを付与
foreach ($adminEmails as $email) {
    $user = User::where('email', $email)->first();
    if ($user) {
        $user->syncRoles('super_admin');
        echo "✅ {$user->name} ({$email}) に super_admin ロールを設定しました\n";
    } else {
        // ユーザーが存在しない場合、Naoki Takahashiのアカウントを作成
        if ($email === 'naoki@yumeno-marketing.jp') {
            $user = User::create([
                'name' => 'Naoki Takahashi',
                'email' => 'naoki@yumeno-marketing.jp',
                'password' => bcrypt('Takahashi5000'),
                'email_verified_at' => now(),
                'role' => 'superadmin',
                'is_active' => true
            ]);
            $user->syncRoles('super_admin');
            echo "✅ Naoki Takahashi アカウントを作成し、super_admin ロールを設定しました\n";
        } else {
            echo "⚠️  {$email} のユーザーが見つかりません\n";
        }
    }
}

// その他の管理者権限を持つユーザーも確認
$superadminUsers = User::where('role', 'superadmin')->get();
foreach ($superadminUsers as $user) {
    if (!in_array($user->email, $adminEmails)) {
        $user->syncRoles('super_admin');
        echo "✅ {$user->name} ({$user->email}) に super_admin ロールを設定しました\n";
    }
}

echo "\n=== 設定完了 ===\n";
echo "super_admin ロールを持つユーザー一覧:\n";
$superAdmins = User::role('super_admin')->get();
foreach ($superAdmins as $admin) {
    echo "- {$admin->name} ({$admin->email})\n";
}

exit();
ENDPHP

echo "=== 権限設定が完了しました ==="
echo "管理画面URL: https://reservation.meno-training.com/admin/login"

ENDSSH

echo "スクリプト実行完了！"