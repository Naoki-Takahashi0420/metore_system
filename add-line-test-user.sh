#!/bin/bash
# 本番環境の.envにLINE_TEST_USER_IDを追加

echo "=== Adding LINE_TEST_USER_ID to production .env ==="

cd /var/www/html

# 既存の行を削除
sudo sed -i '/LINE_TEST_USER_ID/d' .env

# 新しい行を追加
echo "" | sudo tee -a .env
echo "# LINE Test User ID (開発者用)" | sudo tee -a .env
echo "LINE_TEST_USER_ID=Uc37e9137beadca4a6d5c04aaada19ab1" | sudo tee -a .env

echo "✅ LINE_TEST_USER_ID added to .env"

# キャッシュクリア
sudo php artisan config:clear

echo "✅ Config cache cleared"
