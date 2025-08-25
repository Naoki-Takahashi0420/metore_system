# シンプルデプロイガイド

## 1. ワンコマンドデプロイ（推奨）

```bash
# EC2秘密鍵を設定
export EC2_KEY="$(cat ~/.ssh/your-key.pem)"

# デプロイ実行
./scripts/manual-deploy.sh
```

## 2. GitHub Actions自動デプロイ

1. GitHubにpush
2. Actions → "Clean Install Production" を手動実行

## 3. 手動デプロイ手順

### 3.1 環境変数準備
```bash
# 本番用.env作成
cat > .env.production << EOF
APP_NAME=Xsyumeno
APP_ENV=production
APP_DEBUG=false
APP_URL=http://13.115.38.179

DB_CONNECTION=mysql
DB_HOST=xsyumeno-db.cbq0ywo44b0p.ap-northeast-1.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=xsyumenodb
DB_USERNAME=admin
DB_PASSWORD=Xsyumeno2024#!

# AWS SNS SMS設定
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=ap-northeast-1
EOF
```

### 3.2 サーバー接続・デプロイ
```bash
# EC2接続
ssh -i ~/.ssh/your-key.pem ubuntu@13.115.38.179

# アプリケーション配置
cd /var/www/html
sudo rm -rf current
git clone https://github.com/Naoki-Takahashi0420/metore_system.git current
cd current

# 環境設定
cp /path/to/.env.production .env

# 依存関係インストール
composer install --no-dev --optimize-autoloader

# Laravel初期設定
php artisan key:generate
php artisan migrate:fresh --force
php artisan storage:link

# 管理者ユーザー作成
php artisan tinker
> $user = App\Models\User::create(['name' => 'Admin', 'email' => 'admin@xsyumeno.com', 'password' => Hash::make('password'), 'role' => 'superadmin']);

# 権限設定
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
sudo chmod -R 775 storage bootstrap/cache

# サービス再起動
sudo systemctl restart nginx php8.3-fpm
```

## 4. アクセス情報

- URL: http://13.115.38.179/admin/login
- Email: admin@xsyumeno.com
- Password: password

## トラブルシューティング

### 500エラーの場合
```bash
# デバッグモード有効化
sed -i 's/APP_DEBUG=false/APP_DEBUG=true/' .env
sudo systemctl restart nginx php8.3-fpm

# ログ確認
tail -f storage/logs/laravel.log
```

### 権限エラーの場合
```bash
sudo chown -R www-data:www-data /var/www/html/current
sudo chmod -R 775 storage bootstrap/cache
```