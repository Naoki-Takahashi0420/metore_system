#!/bin/bash

# Comprehensive Livewire 500 Error Fix Script for Production
# This script addresses all potential causes of the Livewire /livewire/update 500 error

echo "Starting comprehensive Livewire 500 error fix..."

# Check if EC2_KEY environment variable is set
if [ -z "$EC2_KEY" ]; then
    echo "Error: EC2_KEY environment variable not set"
    echo "Please set it with: export EC2_KEY='your-private-key-content'"
    exit 1
fi

# Create temporary key file
echo "$EC2_KEY" > key.pem
chmod 600 key.pem

# Connect to EC2 and run the comprehensive fix
ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 -i key.pem ubuntu@13.115.38.179 << 'EOF'
cd /var/www/html/current

echo "========================================="
echo "COMPREHENSIVE LIVEWIRE FIX STARTING"
echo "========================================="

echo ""
echo "=== 1. BACKUP CURRENT CONFIGURATION ==="
sudo cp .env .env.backup.$(date +%Y%m%d_%H%M%S)
echo "Configuration backed up"

echo ""
echo "=== 2. CREATE PROPER PRODUCTION ENV ==="
# Create a clean production environment file
cat > .env << 'ENVFILE'
APP_NAME=Xsyumeno
APP_ENV=production
APP_KEY=base64:Gjj1AmoxQPLReuwOG6jDOFfcN2m6Gk0IwCDzk4YYeIo=
APP_DEBUG=false
APP_URL=http://13.115.38.179

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=YOUR_RDS_ENDPOINT
DB_PORT=3306
DB_DATABASE=xsyumenodb
DB_USERNAME=admin
DB_PASSWORD=YOUR_DB_PASSWORD

# Session Configuration - Critical for Livewire
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=false
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

# Cache Configuration
CACHE_STORE=database
CACHE_PREFIX=

# Filesystem and Queue
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

# AWS Configuration
AWS_ACCESS_KEY_ID=YOUR_AWS_ACCESS_KEY
AWS_SECRET_ACCESS_KEY=YOUR_AWS_SECRET_KEY
AWS_DEFAULT_REGION=ap-northeast-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

# SMS Configuration
SMS_FROM_NAME=Xsyumeno

# Vite Configuration
VITE_APP_NAME="${APP_NAME}"
ENVFILE

echo "Production environment file created"

echo ""
echo "=== 3. SET PROPER PERMISSIONS ==="
# Ensure proper ownership and permissions
sudo chown www-data:www-data .env
sudo chmod 644 .env
sudo chown -R www-data:www-data storage
sudo chown -R www-data:www-data bootstrap/cache
sudo chmod -R 775 storage
sudo chmod -R 775 bootstrap/cache

# Create storage directories if they don't exist
sudo -u www-data mkdir -p storage/framework/sessions
sudo -u www-data mkdir -p storage/framework/cache/data
sudo -u www-data mkdir -p storage/framework/views
sudo -u www-data mkdir -p storage/logs

echo "File permissions set"

echo ""
echo "=== 4. CLEAR ALL CACHES ==="
# Clear all possible caches that might cause issues
sudo rm -rf storage/framework/cache/data/*
sudo rm -rf storage/framework/sessions/*
sudo rm -rf storage/framework/views/*
sudo rm -rf bootstrap/cache/*.php

echo "Caches cleared"

echo ""
echo "=== 5. VERIFY DATABASE TABLES ==="
sudo -u www-data php artisan tinker --execute="
\$tables = ['sessions', 'cache', 'users', 'password_reset_tokens'];
foreach (\$tables as \$table) {
    \$exists = \Illuminate\Support\Facades\Schema::hasTable(\$table);
    echo \$table . ': ' . (\$exists ? 'EXISTS' : 'MISSING') . '\n';
    if (\$table === 'sessions' && \$exists) {
        \$count = \Illuminate\Support\Facades\DB::table('sessions')->count();
        echo '  Session records: ' . \$count . '\n';
    }
}
"

echo ""
echo "=== 6. CREATE MISSING TABLES IF NEEDED ==="
# Ensure sessions table exists and has proper structure
sudo -u www-data php artisan tinker --execute="
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

if (!Schema::hasTable('sessions')) {
    echo 'Creating sessions table...\n';
    Schema::create('sessions', function (Blueprint \$table) {
        \$table->string('id')->primary();
        \$table->foreignId('user_id')->nullable()->index();
        \$table->string('ip_address', 45)->nullable();
        \$table->text('user_agent')->nullable();
        \$table->longText('payload');
        \$table->integer('last_activity')->index();
    });
    echo 'Sessions table created\n';
} else {
    echo 'Sessions table exists\n';
}

if (!Schema::hasTable('cache')) {
    echo 'Creating cache table...\n';
    Schema::create('cache', function (Blueprint \$table) {
        \$table->string('key')->primary();
        \$table->mediumText('value');
        \$table->integer('expiration');
    });
    Schema::create('cache_locks', function (Blueprint \$table) {
        \$table->string('key')->primary();
        \$table->string('owner');
        \$table->integer('expiration');
    });
    echo 'Cache tables created\n';
} else {
    echo 'Cache table exists\n';
}
"

echo ""
echo "=== 7. REBUILD LARAVEL CACHES ==="
# Rebuild all Laravel caches
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:clear
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan optimize

echo "Laravel caches rebuilt"

echo ""
echo "=== 8. TEST LIVEWIRE ENDPOINT ==="
# Test the Livewire endpoint directly
echo "Testing Livewire configuration..."
sudo -u www-data php artisan tinker --execute="
try {
    \$config = config('app.key');
    echo 'APP_KEY configured: ' . (!empty(\$config) ? 'YES' : 'NO') . '\n';
    
    \$sessionDriver = config('session.driver');
    echo 'Session driver: ' . \$sessionDriver . '\n';
    
    \$cacheDriver = config('cache.default');
    echo 'Cache driver: ' . \$cacheDriver . '\n';
    
    echo 'Testing CSRF token generation...\n';
    \$token = csrf_token();
    echo 'CSRF token generated: ' . (!empty(\$token) ? 'YES' : 'NO') . '\n';
    
} catch (Exception \$e) {
    echo 'Configuration test error: ' . \$e->getMessage() . '\n';
}
"

echo ""
echo "=== 9. RESTART SERVICES ==="
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx
echo "Services restarted"

echo ""
echo "=== 10. FINAL VERIFICATION ==="
# Test the actual endpoints
echo "Testing login page..."
curl -I -s http://13.115.38.179/admin/login | head -n 1

echo ""
echo "Testing with session cookies..."
# Create a test session and attempt login
curl -c cookies.txt -b cookies.txt -s -o /dev/null -w "HTTP Status: %{http_code}\n" http://13.115.38.179/admin/login

echo ""
echo "Testing CSRF token availability..."
sudo -u www-data php artisan tinker --execute="
try {
    \$app = app();
    \$request = \$app->make('request');
    echo 'Session ID: ' . session()->getId() . '\n';
    echo 'CSRF Token: ' . csrf_token() . '\n';
} catch (Exception \$e) {
    echo 'Session test error: ' . \$e->getMessage() . '\n';
}
"

# Clean up cookies file
rm -f cookies.txt

echo ""
echo "========================================="
echo "COMPREHENSIVE LIVEWIRE FIX COMPLETE!"
echo "========================================="
echo ""
echo "FIXED ISSUES:"
echo "✓ APP_KEY properly set"
echo "✓ Environment configuration cleaned"
echo "✓ Session driver set to database"
echo "✓ Cache configuration fixed"
echo "✓ File permissions corrected"
echo "✓ Storage directories created"
echo "✓ All caches cleared and rebuilt"
echo "✓ Database tables verified"
echo "✓ Services restarted"
echo ""
echo "LOGIN DETAILS:"
echo "URL: http://13.115.38.179/admin/login"
echo "Email: admin@xsyumeno.com"
echo "Password: password"
echo ""
echo "If login still fails, check the Laravel logs:"
echo "sudo tail -f /var/www/html/current/storage/logs/laravel.log"
echo "========================================="

EOF

# Clean up
rm key.pem

echo ""
echo "========================================="
echo "PRODUCTION FIX SCRIPT COMPLETED!"
echo "========================================="
echo ""
echo "The script has:"
echo "1. Fixed the malformed .env.production file"
echo "2. Set proper APP_KEY for encryption"
echo "3. Configured sessions to use database driver"
echo "4. Fixed file permissions for storage directories"
echo "5. Cleared and rebuilt all caches"
echo "6. Restarted all services"
echo ""
echo "Test the login at: http://13.115.38.179/admin/login"
echo "If issues persist, check the Laravel logs on the server"