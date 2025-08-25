#!/bin/bash

echo "=== Complete Fix Script ==="

# Add PHP repository
add-apt-repository ppa:ondrej/php -y
apt update

# Install everything
apt install -y nginx php8.3 php8.3-fpm php8.3-mysql php8.3-xml php8.3-mbstring php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd composer unzip mysql-client

# Setup application
cd /var/www/html
unzip -o main.zip
mv -f metore_system-main current
cd current

# Environment
cat > .env << 'EOF'
APP_NAME=Xsyumeno
APP_ENV=production
APP_KEY=base64:FhDmJJEPuQPAz6t5HMt6qTVSYLs7pJg7xLDgBEcVHHg=
APP_DEBUG=false
APP_URL=http://13.231.41.238

DB_CONNECTION=mysql
DB_HOST=xsyumeno-db.cbq0ywo44b0p.ap-northeast-1.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=xsyumenodb
DB_USERNAME=admin
DB_PASSWORD=Xsyumeno2024#!

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
EOF

# Composer
composer install --no-dev --optimize-autoloader

# Laravel
php artisan storage:link
php artisan migrate:fresh --force

# Admin user
php artisan tinker --execute="
\$user = App\Models\User::create([
    'name' => 'Administrator',
    'email' => 'admin@xsyumeno.com',
    'password' => Hash::make('password'),
    'role' => 'superadmin',
    'is_active' => true,
    'email_verified_at' => now()
]);
"

# Permissions
chown -R www-data:www-data /var/www/html/current
chmod -R 775 storage bootstrap/cache

# Nginx
cat > /etc/nginx/sites-available/default << 'NGINX'
server {
    listen 80 default_server;
    root /var/www/html/current/public;
    index index.php;
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
    }
}
NGINX

# Restart
systemctl restart nginx php8.3-fpm

echo "=== COMPLETE! ==="
echo "URL: http://13.231.41.238/admin/login"