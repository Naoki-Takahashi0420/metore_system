#!/bin/bash

# EC2インスタンス初期セットアップスクリプト
# Ubuntu 22.04 LTS 用

set -e

echo "=== Xsyumeno EC2 Setup Started ==="

# システムアップデート
sudo apt update && sudo apt upgrade -y

# 必要なパッケージのインストール
sudo apt install -y \
    nginx \
    mysql-server \
    php8.4 \
    php8.4-fpm \
    php8.4-mysql \
    php8.4-xml \
    php8.4-mbstring \
    php8.4-zip \
    php8.4-gd \
    php8.4-curl \
    php8.4-bcmath \
    php8.4-json \
    php8.4-tokenizer \
    php8.4-ctype \
    php8.4-fileinfo \
    unzip \
    curl \
    git \
    awscli \
    nodejs \
    npm

# Composer インストール
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# MySQL セットアップ
sudo mysql_secure_installation <<EOF

y
StrongPassword123!
StrongPassword123!
y
y
y
y
EOF

# データベース作成
sudo mysql -u root -pStrongPassword123! <<EOF
CREATE DATABASE xsyumeno CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'xsyumeno'@'localhost' IDENTIFIED BY 'XsyumenoPassword123!';
GRANT ALL PRIVILEGES ON xsyumeno.* TO 'xsyumeno'@'localhost';
FLUSH PRIVILEGES;
EOF

# PHP設定
sudo sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/' /etc/php/8.4/fpm/php.ini
sudo sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 20M/' /etc/php/8.4/fpm/php.ini
sudo sed -i 's/post_max_size = 8M/post_max_size = 20M/' /etc/php/8.4/fpm/php.ini

# Nginx設定
sudo tee /etc/nginx/sites-available/xsyumeno <<EOF
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php index.html index.htm;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    # Laravel specific
    location ~ ^/(.*)/storage/(.*)$ {
        alias /var/www/html/storage/app/public/\$2;
    }
}
EOF

# サイト有効化
sudo ln -sf /etc/nginx/sites-available/xsyumeno /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# ディレクトリ作成と権限設定
sudo mkdir -p /var/www/html
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html

# SSL証明書用のディレクトリ作成（将来のため）
sudo mkdir -p /etc/ssl/xsyumeno

# ファイアウォール設定
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw --force enable

# サービス開始・有効化
sudo systemctl start nginx
sudo systemctl enable nginx
sudo systemctl start php8.4-fpm
sudo systemctl enable php8.4-fpm
sudo systemctl start mysql
sudo systemctl enable mysql

# AWS CLI設定（後で手動で実行）
echo "AWS CLI configuration will be done manually"

# ログローテーション設定
sudo tee /etc/logrotate.d/xsyumeno <<EOF
/var/www/html/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
}
EOF

# Let's Encrypt 準備（オプション）
sudo snap install core; sudo snap refresh core
sudo snap install --classic certbot
sudo ln -sf /snap/bin/certbot /usr/bin/certbot

# 環境変数設定例ファイル作成
sudo tee /var/www/html/.env.production <<EOF
APP_NAME="Xsyumeno"
APP_ENV=production
APP_KEY=base64:$(openssl rand -base64 32)
APP_DEBUG=false
APP_URL=http://$(curl -s http://169.254.169.254/latest/meta-data/public-ipv4)

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=xsyumeno
DB_USERNAME=xsyumeno
DB_PASSWORD=XsyumenoPassword123!

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

# AWS SNS設定
AWS_ACCESS_KEY_ID=your_access_key_here
AWS_SECRET_ACCESS_KEY=your_secret_key_here
AWS_DEFAULT_REGION=ap-northeast-1
SNS_REGION=ap-northeast-1
SMS_FROM=Xsyumeno

# メール設定
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@xsyumeno.com"
MAIL_FROM_NAME="\${APP_NAME}"
EOF

echo "=== EC2 Setup Completed ==="
echo "Next steps:"
echo "1. Configure AWS CLI: aws configure"
echo "2. Set up SSL certificate with Let's Encrypt"
echo "3. Upload your application code"
echo "4. Copy .env.production to .env and update values"
echo "5. Run: composer install --no-dev --optimize-autoloader"
echo "6. Run: php artisan migrate --force"
echo "7. Run: php artisan storage:link"
echo "8. Set proper permissions: chown -R www-data:www-data /var/www/html"