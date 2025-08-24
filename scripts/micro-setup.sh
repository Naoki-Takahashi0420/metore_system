#!/bin/bash

# 超軽量 t3.micro 用セットアップ
# 月間1000アクセス対応

set -e

echo "🚀 Xsyumeno Micro Setup Started"

# システム最適化
sudo apt update
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
    unzip \
    curl \
    git

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# MySQL軽量設定
sudo mysql_secure_installation <<EOF

n
y
StrongPass123!
StrongPass123!
y
y
y
y
EOF

# データベース作成
sudo mysql -u root -pStrongPass123! <<EOF
CREATE DATABASE xsyumeno;
CREATE USER 'xsyumeno'@'localhost' IDENTIFIED BY 'XsyumenoPass123!';
GRANT ALL PRIVILEGES ON xsyumeno.* TO 'xsyumeno'@'localhost';
FLUSH PRIVILEGES;
EOF

# PHP軽量化設定
sudo tee /etc/php/8.4/fpm/pool.d/www.conf <<EOF
[www]
user = www-data
group = www-data
listen = /run/php/php8.4-fpm.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
EOF

# Nginx軽量設定
sudo tee /etc/nginx/sites-available/xsyumeno <<EOF
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;

    # ログ最小化
    access_log off;
    error_log /var/log/nginx/error.log crit;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_cache_valid 200 60m;
    }

    # 静的ファイルキャッシュ
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 1M;
        add_header Cache-Control "public, immutable";
    }
}
EOF

sudo ln -sf /etc/nginx/sites-available/xsyumeno /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# ディレクトリ設定
sudo mkdir -p /var/www/html
sudo chown -R www-data:www-data /var/www/html

# 最低限のセキュリティ
sudo ufw allow ssh
sudo ufw allow 'Nginx HTTP'
sudo ufw --force enable

# サービス開始
sudo systemctl enable --now nginx php8.4-fpm mysql

# スワップファイル作成（t3.microは1GBしかないので）
sudo fallocate -l 1G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab

# 環境変数テンプレート
sudo tee /var/www/html/.env.micro <<EOF
APP_NAME=Xsyumeno
APP_ENV=production
APP_KEY=base64:$(openssl rand -base64 32)
APP_DEBUG=false
APP_URL=http://$(curl -s http://169.254.169.254/latest/meta-data/public-ipv4)

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=xsyumeno
DB_USERNAME=xsyumeno
DB_PASSWORD=XsyumenoPass123!

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

LOG_LEVEL=error
LOG_CHANNEL=single
EOF

echo "✅ Micro setup completed!"
echo ""
echo "Next steps:"
echo "1. Copy .env.micro to .env"
echo "2. Deploy your app"
echo "3. Run: php artisan migrate --force"
echo "4. You're ready for 1000+ users! 🎉"