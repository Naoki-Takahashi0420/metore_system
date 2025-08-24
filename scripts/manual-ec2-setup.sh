#!/bin/bash

# Manual EC2 Setup for first deployment
echo "Setting up EC2 for first deployment..."

# Connect to EC2 and run initial setup
ssh -o StrictHostKeyChecking=no -i /Applications/MAMP/htdocs/Xsyumeno-main/xsyumeno-key.pem ec2-user@13.115.38.179 << 'EOF'
# Create application directory
sudo mkdir -p /var/www/html
sudo chown -R ec2-user:ec2-user /var/www/html

# Create .env.production file
cat > /var/www/html/.env.production << 'ENVEOF'
APP_NAME=Xsyumeno
APP_ENV=production
APP_KEY=base64:YOUR_APP_KEY_HERE
APP_DEBUG=false
APP_URL=http://13.115.38.179

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# Database Configuration
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

# AWS Configuration
AWS_ACCESS_KEY_ID=AKIAT7ELA2O64X2PK547
AWS_SECRET_ACCESS_KEY=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
AWS_DEFAULT_REGION=ap-northeast-1

# AWS SNS Configuration for SMS
AWS_SNS_KEY=AKIAT7ELA2O64X2PK547
AWS_SNS_SECRET=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
AWS_SNS_REGION=ap-northeast-1
AWS_SNS_SENDER_ID=Xsyumeno
AWS_SNS_SMS_TYPE=Transactional

# Filament Configuration
FILAMENT_FILESYSTEM_DISK=public
ENVEOF

# Create Nginx configuration
sudo tee /etc/nginx/conf.d/laravel.conf > /dev/null << 'NGINXEOF'
server {
    listen 80;
    server_name _;
    root /var/www/html/current/public;
    index index.php index.html;

    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
NGINXEOF

# Remove default Nginx config
sudo rm -f /etc/nginx/conf.d/default.conf

# Test and restart Nginx
sudo nginx -t
sudo systemctl restart nginx
sudo systemctl restart php-fpm

echo "EC2 initial setup completed!"
echo "Ready for GitHub Actions deployment"

EOF