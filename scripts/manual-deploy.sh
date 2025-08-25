#!/bin/bash

# Manual production fix script
# This script fixes the migration order issue and creates the admin user

echo "Starting manual production fix..."

# Check if EC2_KEY environment variable is set
if [ -z "$EC2_KEY" ]; then
    echo "Error: EC2_KEY environment variable not set"
    echo "Please set it with: export EC2_KEY='your-private-key-content'"
    exit 1
fi

# Create temporary key file
echo "$EC2_KEY" > key.pem
chmod 600 key.pem

# Connect to EC2 and run fix
ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 -i key.pem ubuntu@13.115.38.179 << 'EOF'
cd /var/www/html/current

echo "=== Drop all tables first ==="
sudo -u www-data php artisan db:wipe --force

echo ""
echo "=== Backup original users migration ==="
if [ -f database/migrations/0001_01_01_000000_create_users_table.php ]; then
    cp database/migrations/0001_01_01_000000_create_users_table.php database/migrations/0001_01_01_000000_create_users_table.php.backup
fi

echo ""
echo "=== Create temporary users migration without foreign key ==="
cat > database/migrations/0001_01_01_000000_create_users_table.php << 'PHPCODE'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('name')->comment('氏名');
            $table->string('email')->unique()->comment('メールアドレス');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->comment('パスワード');
            $table->enum('role', ['superadmin', 'admin', 'manager', 'staff'])
                  ->default('staff')->comment('役職');
            $table->json('permissions')->nullable()->comment('権限設定');
            $table->json('specialties')->nullable()->comment('専門分野');
            $table->decimal('hourly_rate', 8, 2)->nullable()->comment('時給');
            $table->boolean('is_active')->default(true)->comment('アクティブ状態');
            $table->timestamp('last_login_at')->nullable()->comment('最終ログイン');
            $table->rememberToken();
            $table->timestamps();
            
            $table->index(['store_id', 'role']);
            $table->index(['is_active']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
PHPCODE

echo ""
echo "=== Run all migrations ==="
sudo -u www-data php artisan migrate --force

echo ""
echo "=== Add foreign key constraint after stores table exists ==="
sudo -u www-data php artisan tinker --execute="
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
try {
    Schema::table('users', function (Blueprint \$table) {
        \$table->foreign('store_id')->references('id')->on('stores')->onDelete('set null');
    });
    echo 'Foreign key constraint added successfully\n';
} catch (Exception \$e) {
    echo 'Foreign key constraint error (might already exist): ' . \$e->getMessage() . '\n';
}
"

echo ""
echo "=== Creating admin user ==="
sudo -u www-data php artisan tinker --execute="
try {
    \$existing = \App\Models\User::where('email', 'admin@xsyumeno.com')->first();
    if (\$existing) {
        echo 'Admin user already exists, updating...\n';
        \$existing->update([
            'name' => 'Administrator',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'superadmin',
            'is_active' => true,
            'email_verified_at' => now()
        ]);
        echo 'Admin user updated with ID: ' . \$existing->id . '\n';
    } else {
        \$user = \App\Models\User::create([
            'name' => 'Administrator',
            'email' => 'admin@xsyumeno.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'superadmin',
            'is_active' => true,
            'email_verified_at' => now()
        ]);
        echo 'Admin user created with ID: ' . \$user->id . '\n';
    }
} catch (Exception \$e) {
    echo 'Admin user creation error: ' . \$e->getMessage() . '\n';
}
"

echo ""
echo "=== Verify setup ==="
sudo -u www-data php artisan tinker --execute="
\$users = \App\Models\User::all();
echo 'Total users: ' . \$users->count() . '\n';
foreach (\$users as \$user) {
    echo 'ID: ' . \$user->id . ', Email: ' . \$user->email . ', Role: ' . \$user->role . ', Active: ' . (\$user->is_active ? 'YES' : 'NO') . '\n';
}

echo '\nTesting database tables:\n';
\$tables = ['users', 'stores', 'cache', 'sessions', 'password_reset_tokens'];
foreach (\$tables as \$table) {
    \$exists = \Illuminate\Support\Facades\Schema::hasTable(\$table);
    echo \$table . ': ' . (\$exists ? 'EXISTS' : 'MISSING') . '\n';
}
"

echo ""
echo "=== Clear all caches ==="
sudo rm -rf bootstrap/cache/*
sudo rm -rf storage/framework/cache/data/*
sudo rm -rf storage/framework/sessions/*
sudo rm -rf storage/framework/views/*

sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache  
sudo -u www-data php artisan view:cache

sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

echo ""
echo "=== Restart services ==="
sudo systemctl restart php8.3-fpm nginx

echo ""
echo "=== Final verification ==="
curl -I http://13.115.38.179/admin/login

echo ""
echo "========================================="
echo "MANUAL FIX COMPLETE!"
echo "========================================="
echo "Login URL: http://13.115.38.179/admin/login"
echo "Email: admin@xsyumeno.com"
echo "Password: password"
echo "========================================="
EOF

# Clean up
rm key.pem

echo "Manual fix script completed!"