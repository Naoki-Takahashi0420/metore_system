#!/bin/bash
cd /var/www/html
if ! grep -q "LINE_TEST_USER_ID" .env; then
  echo "" >> .env
  echo "# LINE Test User ID (開発者用)" >> .env
  echo "LINE_TEST_USER_ID=Uc37e9137beadca4a6d5c04aaada19ab1" >> .env
  echo "Added LINE_TEST_USER_ID"
else
  echo "LINE_TEST_USER_ID already exists"
fi
php artisan config:clear
tail -5 .env
