#!/bin/bash
yum update -y
yum install -y nginx php82 php82-fpm php82-mysqlnd php82-bcmath php82-xml php82-mbstring php82-gd php82-curl php82-zip git

# Install Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Install Node.js
curl -sL https://rpm.nodesource.com/setup_18.x | bash -
yum install -y nodejs

# Configure PHP-FPM
sed -i 's/user = apache/user = nginx/g' /etc/php-fpm.d/www.conf
sed -i 's/group = apache/group = nginx/g' /etc/php-fpm.d/www.conf

# Start services
systemctl start php-fpm
systemctl enable php-fpm
systemctl start nginx
systemctl enable nginx

# Create deployment directory
mkdir -p /var/www/html
chown -R nginx:nginx /var/www/html