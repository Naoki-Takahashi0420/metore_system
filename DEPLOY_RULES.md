# デプロイルール - 二度と壊さないために

## 絶対に守るルール

### 1. 相対パスを使わない
❌ **ダメな例:**
```bash
chmod -R 775 storage
chmod 600 .ssh/authorized_keys
```

✅ **良い例:**
```bash
chmod -R 775 /var/www/html/storage
chmod 600 /home/ubuntu/.ssh/authorized_keys
```

### 2. ファイル/ディレクトリの存在確認
❌ **ダメな例:**
```bash
chmod -R 775 /var/www/html/storage
```

✅ **良い例:**
```bash
if [ -d "/var/www/html/storage" ]; then
  chmod -R 775 /var/www/html/storage
fi
```

### 3. エラーハンドリング
✅ **必須設定:**
```bash
set -e  # エラーで即停止
set -u  # 未定義変数でエラー
set -o pipefail  # パイプのエラーも検出
```

### 4. デプロイ前の検証
```bash
# SCPが成功したか確認
if scp deploy.tar.gz user@host:/tmp/; then
  echo "Upload successful"
else
  echo "Upload failed, aborting"
  exit 1
fi
```

### 5. 破壊的コマンドの前に確認
```bash
# rmの前にパスを確認
TARGET_DIR="/var/www/html"
if [ "$TARGET_DIR" = "/" ]; then
  echo "ERROR: Attempting to delete root!"
  exit 1
fi
sudo rm -rf $TARGET_DIR/*
```

## テスト方法

### ローカルテスト
```bash
# デプロイスクリプトをローカルでテスト
./scripts/test-deploy-locally.sh
```

### ステージング環境
本番にデプロイする前に、必ずステージング環境でテスト

### 監視
- デプロイ後、必ずSSH接続を確認
- HTTP/HTTPSレスポンスを確認
- ログを確認

## 緊急時の対応

### SSHが壊れた場合
1. EC2 Serial Consoleでアクセス
2. 以下のコマンドで修復:
```bash
chmod 700 /home/ubuntu/.ssh
chmod 600 /home/ubuntu/.ssh/authorized_keys
chown -R ubuntu:ubuntu /home/ubuntu/.ssh
```

### アプリケーションが動かない場合
1. エラーログ確認: `/var/log/nginx/error.log`
2. Laravel ログ: `/var/www/html/storage/logs/laravel.log`
3. PHP-FPM ログ: `journalctl -u php8.1-fpm`

## チェックリスト

デプロイ前に必ず確認:
- [ ] 全てのパスが絶対パス
- [ ] ファイル存在確認がある
- [ ] set -e が設定されている
- [ ] ローカルでテスト済み
- [ ] バックアップがある