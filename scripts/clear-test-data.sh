#!/bin/bash
set -e

echo "Clearing test store data..."

cd /var/www/html/current

# Delete all stores (keeping admin users)
sudo -u www-data php artisan tinker --execute="
\App\Models\Store::truncate();
echo 'Test stores deleted successfully!';
"

echo "Test data cleared, admin users preserved"