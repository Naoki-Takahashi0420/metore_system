# リアルタイム予約通知 デプロイ手順

## 概要
新規予約が入った際に、管理画面にリアルタイムで通知（音+点滅）を表示する機能。
Laravel Reverbを使用したWebSocket通信。

---

## 本番デプロイ手順

### Step 1: コードをデプロイ（通知機能は無効のまま）

```bash
# GitHub Actionsでデプロイ、または手動で
git pull origin main

# 依存関係インストール
composer install --no-dev --optimize-autoloader
npm install && npm run build

# キャッシュクリア
php artisan optimize:clear
```

この時点では `BROADCAST_CONNECTION=log` のままなので既存機能に影響なし。

---

### Step 2: Supervisorのインストールと設定

```bash
# EC2にSSH接続
ssh -i ~/.ssh/your-key.pem ubuntu@54.64.54.226

# Supervisorインストール
sudo apt update
sudo apt install -y supervisor

# Reverb用の設定ファイル作成
sudo nano /etc/supervisor/conf.d/reverb.conf
```

以下の内容を貼り付け:

```ini
[program:reverb]
process_name=%(program_name)s
command=php /var/www/html/artisan reverb:start
directory=/var/www/html
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/reverb.log
stopwaitsecs=3600
```

設定を反映:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start reverb
```

確認:

```bash
sudo supervisorctl status reverb
# reverb                           RUNNING   pid 12345, uptime 0:00:10
```

---

### Step 3: Nginx WebSocketプロキシ設定

```bash
sudo nano /etc/nginx/sites-available/default
```

`server` ブロック内に以下を追加:

```nginx
# WebSocket proxy for Laravel Reverb
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 60s;
    proxy_send_timeout 60s;
}
```

Nginx再起動:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

### Step 4: 本番環境変数を設定

```bash
sudo nano /var/www/html/.env
```

以下を追加/変更:

```env
# Laravel Reverb (WebSocket Server)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=metore-app
REVERB_APP_KEY=metore-realtime-key
REVERB_APP_SECRET=metore-realtime-secret
REVERB_HOST=reservation.meno-training.com
REVERB_PORT=443
REVERB_SCHEME=https

# Reverb Server Settings
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# Vite用
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

キャッシュクリア:

```bash
php artisan config:clear
php artisan cache:clear
```

---

### Step 5: 動作確認

1. ブラウザで管理画面を開く: https://reservation.meno-training.com/admin
2. 開発者ツール（F12）のConsoleを確認
3. 別タブで予約フォームから新規予約を作成
4. 管理画面に通知が表示されることを確認

---

## トラブルシューティング

### Reverbサーバーが起動しない

```bash
# ログ確認
tail -f /var/www/html/storage/logs/reverb.log

# 手動で起動してエラー確認
cd /var/www/html
sudo -u www-data php artisan reverb:start --debug
```

### WebSocket接続エラー

```bash
# Nginxログ確認
tail -f /var/log/nginx/error.log

# ポート8080が開いているか確認
sudo lsof -i :8080
```

### 通知が届かない

1. ブラウザのConsoleでEcho接続エラーを確認
2. `php artisan tinker` でイベント発火テスト:

```php
$reservation = App\Models\Reservation::first();
broadcast(new App\Events\ReservationCreated($reservation));
```

---

## ロールバック方法

問題が発生した場合、即座に無効化できます:

```bash
# .envを編集
sudo nano /var/www/html/.env

# この行を変更
BROADCAST_CONNECTION=log

# キャッシュクリア
php artisan config:clear

# Reverbサーバー停止（任意）
sudo supervisorctl stop reverb
```

これで従来通りの動作に戻ります。

---

## 監視項目

- Reverbサーバーのメモリ使用量
- WebSocket接続数
- `/var/www/html/storage/logs/reverb.log` のエラー

---

## 関連ファイル

- `app/Events/ReservationCreated.php` - ブロードキャストイベント
- `config/reverb.php` - Reverb設定
- `config/broadcasting.php` - Broadcasting設定
- `resources/js/bootstrap.js` - Laravel Echo設定
- `resources/views/components/realtime-notification.blade.php` - 通知UI

---

**作成日**: 2025-12-03
**作成者**: Claude Code
