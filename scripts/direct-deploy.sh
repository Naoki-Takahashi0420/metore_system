#!/bin/bash

echo "=== Direct Deploy to EC2 ==="

# Package application
echo "Creating deployment package..."
tar -czf /tmp/deploy.tar.gz \
  --exclude=node_modules \
  --exclude=.git \
  --exclude=.env \
  --exclude=deploy.tar.gz \
  --exclude=storage/logs/* \
  --exclude=storage/framework/cache/* \
  --exclude=storage/framework/sessions/* \
  --exclude=storage/framework/views/* \
  --exclude=bootstrap/cache/* \
  .

# Upload to EC2
echo "Uploading to EC2..."
scp -o StrictHostKeyChecking=no -o ConnectTimeout=10 -i ~/.ssh/xsyumeno-ec2-key.pem \
  /tmp/deploy.tar.gz ubuntu@3.112.39.129:/tmp/

# Deploy on EC2
echo "Deploying on EC2..."
ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -i ~/.ssh/xsyumeno-ec2-key.pem ubuntu@3.112.39.129 << 'DEPLOY'
# Create directory
sudo mkdir -p /var/www/html
sudo rm -rf /var/www/html/*

# Extract files
cd /var/www/html
sudo tar -xzf /tmp/deploy.tar.gz
rm /tmp/deploy.tar.gz

# Create .env
sudo tee .env << 'ENV' > /dev/null
APP_NAME=Xsyumeno
APP_ENV=production
APP_KEY=base64:FhDmJJEPuQPAz6t5HMt6qTVSYLs7pJg7xLDgBEcVHHg=
APP_DEBUG=false
APP_URL=http://3.112.39.129

DB_CONNECTION=mysql
DB_HOST=xsyumeno-db.cbq0ywo44b0p.ap-northeast-1.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=xsyumenodb
DB_USERNAME=admin
DB_PASSWORD=Xsyumeno2024#!
ENV

# Install composer if needed
if ! command -v composer &> /dev/null; then
  curl -sS https://getcomposer.org/installer | php
  sudo mv composer.phar /usr/local/bin/composer
fi

# Install dependencies
sudo composer install --no-dev --optimize-autoloader

# Laravel setup
sudo php artisan key:generate
sudo php artisan storage:link
sudo php artisan config:clear
sudo php artisan config:cache
sudo php artisan route:cache
sudo php artisan view:cache

# Permissions
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html
sudo chmod -R 775 storage bootstrap/cache

# Restart services
sudo systemctl restart nginx php8.3-fpm

echo "=== Deploy Complete ==="
echo "Testing..."
curl -I http://localhost/
DEPLOY

# Clean up
rm /tmp/deploy.tar.gz

echo "=== SUCCESS ==="
echo "URL: http://3.112.39.129"
echo "Admin: http://3.112.39.129/admin/login"