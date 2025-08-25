#!/bin/bash

# EC2 Instance Connectで接続して、このスクリプトを実行するだけ
# Usage: EC2にログイン後、以下を実行
# curl -s https://raw.githubusercontent.com/Naoki-Takahashi0420/metore_system/main/deploy-manual.sh | bash

echo "🚀 Starting deployment..."

cd /var/www/html || {
    sudo mkdir -p /var/www/html
    cd /var/www/html
}

# Pull latest code
if [ -d ".git" ]; then
    echo "📥 Pulling latest changes..."
    sudo git pull origin main
else
    echo "📦 Cloning repository..."
    sudo git clone https://github.com/Naoki-Takahashi0420/metore_system.git .
fi

# Install dependencies if needed
if [ -f "composer.json" ]; then
    echo "📚 Installing dependencies..."
    sudo composer install --no-dev --optimize-autoloader
fi

# Laravel specific
if [ -f "artisan" ]; then
    echo "🔧 Running Laravel commands..."
    sudo php artisan migrate --force
    sudo php artisan config:cache
    sudo php artisan route:cache
    sudo php artisan view:cache
fi

# Set permissions
echo "🔐 Setting permissions..."
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html
[ -d "storage" ] && sudo chmod -R 775 storage
[ -d "bootstrap/cache" ] && sudo chmod -R 775 bootstrap/cache

# Restart services
echo "♻️ Restarting services..."
sudo systemctl restart php8.3-fpm 2>/dev/null || sudo systemctl restart php-fpm
sudo systemctl restart nginx

echo "✅ Deployment completed!"
echo "🌐 Site should be available at: http://13.158.240.0"