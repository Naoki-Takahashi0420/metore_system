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
# AWS credentials should be set as environment variables before running this script
# export AWS_ACCESS_KEY_ID=your_access_key
# export AWS_SECRET_ACCESS_KEY=your_secret_key

cat > .env << EOF
APP_NAME="目のトレーニング"
APP_ENV=production
APP_KEY=base64:FhDmJJEPuQPAz6t5HMt6qTVSYLs7pJg7xLDgBEcVHHg=
APP_DEBUG=false
APP_URL=https://reservation.meno-training.com

APP_LOCALE=ja
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=ja_JP

DB_CONNECTION=mysql
DB_HOST=xsyumeno-db.cbq0ywo44b0p.ap-northeast-1.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=xsyumenodb
DB_USERNAME=admin
DB_PASSWORD=Xsyumeno2024#!

SESSION_DRIVER=database
SESSION_LIFETIME=10080
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

CACHE_DRIVER=database
QUEUE_CONNECTION=database

# メール設定 (AWS SES)
MAIL_MAILER=ses
MAIL_FROM_ADDRESS="noreply@meno-training.com"
MAIL_FROM_NAME="目のトレーニング"

# AWS設定
AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY_ID}
AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY}
AWS_DEFAULT_REGION=ap-northeast-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

# SMS設定（Amazon SNS）
SMS_ENABLED=true
SMS_FROM_NAME=Xsyumeno

# Basic認証設定（本番環境で使用）
BASIC_AUTH_ENABLED=false
BASIC_AUTH_USERNAME=xsyumeno
BASIC_AUTH_PASSWORD=xsyumeno2025!
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