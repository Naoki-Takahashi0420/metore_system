# AWS Infrastructure Setup & CI/CD Guide

## 概要
このプロジェクトは、Laravel + FilamentアプリケーションをAWS EC2 + RDSにデプロイするための自動化設定です。

## 構成
- **EC2**: t3.micro (Webサーバー)
- **RDS**: t3.micro (MySQL 8.0)
- **SNS**: SMS送信用
- **月額費用**: 約2,500-3,500円 + SMS費用

## セットアップ手順

### 1. AWSインフラストラクチャの構築

```bash
cd infrastructure
chmod +x setup-aws.sh
./setup-aws.sh
```

このスクリプトは以下を自動作成します：
- VPC、サブネット、セキュリティグループ
- EC2インスタンス (Nginx + PHP 8.2)
- RDS MySQLデータベース
- SNSトピック
- GitHub Actions用のIAMユーザー

### 2. GitHub Secretsの設定

スクリプト実行後に表示される以下の値をGitHubリポジトリのSecretsに追加：

1. GitHubリポジトリの Settings → Secrets and variables → Actions
2. 以下のシークレットを追加：
   - `AWS_ACCESS_KEY_ID`: IAMアクセスキー
   - `AWS_SECRET_ACCESS_KEY`: IAMシークレットキー
   - `EC2_HOST`: EC2のパブリックIP
   - `EC2_USER`: ec2-user
   - `EC2_SSH_KEY`: xsyumeno-key.pemの内容

### 3. 本番環境設定ファイルの準備

EC2インスタンスにSSH接続して、`.env.production`を配置：

```bash
ssh -i xsyumeno-key.pem ec2-user@<EC2_PUBLIC_IP>
sudo su -
cd /var/www/html
vi .env.production
```

`.env.production`の内容を編集：
- `APP_KEY`: `php artisan key:generate`で生成
- `APP_URL`: EC2のパブリックIPまたはドメイン
- `DB_HOST`: RDSエンドポイント
- `DB_PASSWORD`: setup-aws.shで生成されたパスワード
- `AWS_SNS_KEY`, `AWS_SNS_SECRET`: SNS用の認証情報

### 4. Nginxの設定

EC2上でNginxを設定：

```bash
sudo vi /etc/nginx/conf.d/laravel.conf
```

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/current/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

```bash
sudo systemctl restart nginx
```

### 5. 初回デプロイ

GitHubのmainブランチにプッシュすると自動デプロイが開始されます：

```bash
git add .
git commit -m "Setup CI/CD"
git push origin main
```

## デプロイフロー

1. mainブランチへのプッシュ時に自動実行
2. GitHub Actionsが以下を実行：
   - 依存関係のインストール
   - アセットのビルド
   - EC2へのファイル転送
   - Laravelコマンドの実行
   - サービスの再起動

## トラブルシューティング

### デプロイが失敗する場合
- GitHub Secretsが正しく設定されているか確認
- EC2のセキュリティグループでSSH(22)が開いているか確認
- EC2インスタンスが起動しているか確認

### データベース接続エラー
- RDSのセキュリティグループがEC2からの接続を許可しているか確認
- .env.productionのDB設定が正しいか確認

### SMS送信エラー
- AWS SNSの認証情報が正しいか確認
- SNSの送信制限に達していないか確認

## 手動デプロイ

GitHub Actionsページから「Run workflow」ボタンでいつでも手動デプロイ可能です。

## 監視とログ

- **アプリケーションログ**: `/var/www/html/current/storage/logs/`
- **Nginxログ**: `/var/log/nginx/`
- **PHP-FPMログ**: `/var/log/php-fpm/`

## セキュリティ注意事項

- 本番環境では必ずHTTPS(SSL証明書)を設定してください
- データベースパスワードは定期的に変更してください
- 不要なポートは閉じてください
- AWS IAMの権限は最小限に設定してください