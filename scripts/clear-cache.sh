#!/bin/bash

# 本番環境のキャッシュをクリア
ssh -o StrictHostKeyChecking=no -i ~/.ssh/xsyumeno-ssh-key.pem ubuntu@54.64.54.226 << 'SSH'
cd /var/www/html

echo "=== Clearing all caches ==="
sudo php artisan optimize:clear
sudo php artisan config:clear
sudo php artisan cache:clear
sudo php artisan view:clear
sudo php artisan route:clear

echo ""
echo "=== Rebuilding caches ==="
sudo php artisan config:cache
sudo php artisan filament:assets

echo ""
echo "=== Checking ticket tables ==="
mysql -u xsyumeno_user -p'9Q&sF7#kL2@vR4mN' xsyumeno_db -e "SHOW TABLES LIKE '%ticket%';"

echo ""
echo "=== Checking Filament resources ==="
ls -la app/Filament/Resources/*Ticket*

echo ""
echo "✅ Cache cleared!"
SSH
