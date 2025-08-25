#!/bin/bash

# Log output for debugging
exec > >(tee /var/log/user-data.log)
exec 2>&1

echo "Starting EC2 initialization..."

# Update system
apt-get update
apt-get upgrade -y

# Add PHP repository
add-apt-repository ppa:ondrej/php -y
apt-get update

# Install required packages
apt-get install -y \
    nginx \
    php8.3-fpm \
    php8.3-cli \
    php8.3-common \
    php8.3-mysql \
    php8.3-zip \
    php8.3-gd \
    php8.3-mbstring \
    php8.3-curl \
    php8.3-xml \
    php8.3-bcmath \
    mysql-client \
    git \
    composer \
    unzip

# Configure PHP-FPM
sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/' /etc/php/8.3/fpm/php.ini
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 100M/' /etc/php/8.3/fpm/php.ini
sed -i 's/post_max_size = 8M/post_max_size = 100M/' /etc/php/8.3/fpm/php.ini

# Create application directory
mkdir -p /var/www/html
chown -R www-data:www-data /var/www/html

# Configure Nginx
cat > /etc/nginx/sites-available/default << 'EOF'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    
    root /var/www/html/public;
    index index.php index.html index.htm;
    
    server_name _;
    
    client_max_body_size 100M;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.ht {
        deny all;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

# Restart services
systemctl restart php8.3-fpm
systemctl restart nginx
systemctl enable php8.3-fpm
systemctl enable nginx

# Clone repository from GitHub
cd /var/www/html
git clone https://github.com/Naoki-Takahashi0420/metore_system.git .

# Install composer dependencies
composer install --no-dev --optimize-autoloader

# Set proper permissions
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Create .env file
cat > /var/www/html/.env << 'ENVFILE'
APP_NAME=Xsyumeno
APP_ENV=production
APP_KEY=base64:YOUR_APP_KEY_HERE
APP_DEBUG=false
APP_URL=http://YOUR_EC2_IP

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=xsyumeno-db.cbq0ywo44b0p.ap-northeast-1.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=xsyumenodb
DB_USERNAME=admin
DB_PASSWORD=bms3sH5CS2qtPdTKP7Vi

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

AWS_ACCESS_KEY_ID=AKIAT7ELA2O64X2PK547
AWS_SECRET_ACCESS_KEY=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
AWS_DEFAULT_REGION=ap-northeast-1

AWS_SNS_KEY=AKIAT7ELA2O64X2PK547
AWS_SNS_SECRET=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
AWS_SNS_REGION=ap-northeast-1
AWS_SNS_SENDER_ID=Xsyumeno
AWS_SNS_SMS_TYPE=Transactional

FILAMENT_FILESYSTEM_DISK=public
ENVFILE

# Generate app key
php artisan key:generate --force

# Run migrations
php artisan migrate --force

# Clear and cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "EC2 initialization completed!"