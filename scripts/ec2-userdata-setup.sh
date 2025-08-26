#!/bin/bash

# EC2 UserData script to setup Laravel application
# This will run on EC2 instance boot/reboot

LOG_FILE="/var/log/xsyumeno-setup.log"
exec 1>>$LOG_FILE 2>&1

echo "=========================================="
echo "Starting Xsyumeno Setup at $(date)"
echo "=========================================="

# Wait for network
sleep 10

# Update system
apt-get update

# Install required packages if not present
if ! command -v composer &> /dev/null; then
    echo "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
fi

# Clone repository
cd /tmp
rm -rf xsyumeno-temp
git clone https://github.com/Naoki-Takahashi0420/metore_system.git xsyumeno-temp

if [ ! -d "/tmp/xsyumeno-temp" ]; then
    echo "ERROR: Failed to clone repository"
    exit 1
fi

# Setup application
echo "Setting up application..."
rm -rf /var/www/html/*
cp -r /tmp/xsyumeno-temp/* /var/www/html/
cd /var/www/html

# Create .env file
cat > .env << 'EOF'
APP_NAME=Xsyumeno
APP_ENV=production
APP_KEY=base64:FhDmJJEPuQPAz6t5HMt6qTVSYLs7pJg7xLDgBEcVHHg=
APP_DEBUG=true
APP_URL=http://54.249.2.157

DB_CONNECTION=mysql
DB_HOST=xsyumeno-db.cbq0ywo44b0p.ap-northeast-1.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=xsyumenodb
DB_USERNAME=admin
DB_PASSWORD=Xsyumeno2024#!

LOG_CHANNEL=stack
CACHE_DRIVER=file
SESSION_DRIVER=database
QUEUE_CONNECTION=database

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

AWS_DEFAULT_REGION=ap-northeast-1

SMS_ENABLED=true
SMS_FROM_NAME=Xsyumeno
EOF

# Install Composer dependencies
echo "Installing Composer dependencies..."
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction

# Laravel setup
echo "Setting up Laravel..."
php artisan key:generate
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

# Create admin user
php artisan tinker --execute="
try {
    \$user = App\\Models\\User::firstOrCreate(
        ['email' => 'admin@xsyumeno.com'],
        [
            'name' => 'Administrator',
            'password' => Hash::make('password'),
            'role' => 'superadmin',
            'is_active' => true,
            'email_verified_at' => now()
        ]
    );
    echo 'Admin user created';
} catch (Exception \$e) {
    echo 'User error: ' . \$e->getMessage();
}"

# Set permissions
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
chmod -R 775 storage bootstrap/cache

# Configure Nginx
cat > /etc/nginx/sites-available/default << 'NGINX'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    
    root /var/www/html/public;
    index index.php index.html;
    
    server_name _;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }
    
    location ~ /\.ht {
        deny all;
    }
}
NGINX

# Restart services
systemctl restart nginx php8.1-fpm

# Test
sleep 2
curl -I http://localhost/

echo "=========================================="
echo "Setup completed at $(date)"
echo "=========================================="

# Clean up
rm -rf /tmp/xsyumeno-temp