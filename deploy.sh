#!/bin/bash
# Deployment script for EC2

# Install packages
apt update
apt install -y nginx php8.3 php8.3-fpm php8.3-mysql php8.3-xml php8.3-mbstring php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd composer git mysql-client unzip

# Download code
cd /var/www/html
rm -rf current
wget https://github.com/Naoki-Takahashi0420/metore_system/archive/refs/heads/main.zip
unzip main.zip
mv metore_system-main current
cd current

# Environment setup
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

# Composer install
composer install --no-dev --optimize-autoloader

# Laravel setup
php artisan storage:link
php artisan migrate:fresh --force

# Create admin user
php artisan tinker --execute="
\$user = App\Models\User::create([
    'name' => 'Administrator',
    'email' => 'admin@xsyumeno.com',
    'password' => Hash::make('password'),
    'role' => 'superadmin',
    'is_active' => true,
    'email_verified_at' => now()
]);
echo 'Admin created';
"

# Permissions
chown -R www-data:www-data /var/www/html/current
chmod -R 775 storage bootstrap/cache

# Nginx config
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

# Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
systemctl restart nginx php8.3-fpm

echo "Deployment complete!"
echo "URL: http://13.231.41.238/admin/login"
echo "Email: admin@xsyumeno.com"
echo "Password: password"