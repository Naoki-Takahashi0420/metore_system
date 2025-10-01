#!/bin/bash
# 本番環境のキューワーカー状態確認スクリプト

echo "=== キューワーカー確認 ==="
ps aux | grep 'queue:work\|queue:listen' | grep -v grep || echo "キューワーカーが起動していません"

echo ""
echo "=== 未処理ジョブ確認 ==="
cd /var/www/html
sudo php artisan queue:failed
sudo mysql -u xsyumeno -p'xsyumeno_password' xsyumeno -e "SELECT COUNT(*) as pending_jobs FROM jobs;"

echo ""
echo "=== 最近のログ（LINE関連） ==="
sudo tail -100 /var/www/html/storage/logs/laravel.log | grep -i "LINE\|顧客.*通知" || echo "関連ログなし"
