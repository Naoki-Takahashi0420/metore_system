#!/bin/bash
yum update -y
yum install -y nginx git

# Install PHP 8.3
dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm
dnf module reset php -y
dnf module enable php:remi-8.3 -y
dnf install -y php php-fpm php-mysqlnd php-bcmath php-xml php-mbstring php-gd php-curl php-zip php-intl php-opcache

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