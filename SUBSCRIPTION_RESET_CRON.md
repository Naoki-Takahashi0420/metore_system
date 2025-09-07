# サブスクリプション月次リセット設定ガイド

## 実装済み機能

### 1. 予約完了時の自動カウント
- 管理画面で予約を「完了」にすると、自動的にサブスクリプションの利用回数がカウントされます
- `/app/Filament/Resources/ReservationResource.php` の554行目で実装

### 2. 月次リセットコマンド
- `php artisan subscription:reset-monthly` コマンドを実装
- オプション:
  - `--dry-run`: 実行せずに対象を表示
  - `--force`: 確認なしで実行

## リセットタイミングのロジック

1. **reset_day が設定されている場合**: その日にリセット（例：毎月15日）
2. **billing_start_date がある場合**: 請求開始日と同じ日にリセット
3. **どちらもない場合**: 毎月1日にリセット（デフォルト）

## スケジュール設定方法

### 方法1: Laravelのスケジューラーを使用（推奨）

1. `app/Console/Kernel.php` を作成して以下を追加:

```php
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // 毎日午前0時5分に実行
        $schedule->command('subscription:reset-monthly')
            ->dailyAt('00:05')
            ->appendOutputTo(storage_path('logs/subscription-reset.log'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
```

2. サーバーのcrontabに以下を追加:
```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

### 方法2: 直接crontabに登録

サーバーのcrontabに以下を追加:
```bash
# 毎日午前0時5分に実行
5 0 * * * cd /Applications/MAMP/htdocs/Xsyumeno-main && php artisan subscription:reset-monthly --force >> storage/logs/subscription-reset.log 2>&1
```

### 方法3: GitHub Actionsを使用（本番環境）

`.github/workflows/subscription-reset.yml` を作成:

```yaml
name: Reset Monthly Subscriptions

on:
  schedule:
    # 毎日午前0時5分（UTC）に実行
    - cron: '5 15 * * *'  # 日本時間 0:05
  workflow_dispatch:  # 手動実行も可能

jobs:
  reset:
    runs-on: ubuntu-latest
    steps:
      - name: SSH and reset subscriptions
        uses: appleboy/ssh-action@v0.1.5
        with:
          host: 54.64.54.226
          username: ubuntu
          key: ${{ secrets.SSH_KEY }}
          script: |
            cd /var/www/html
            php artisan subscription:reset-monthly --force
```

## テスト方法

```bash
# ドライランで確認
php artisan subscription:reset-monthly --dry-run

# 実際に実行（確認あり）
php artisan subscription:reset-monthly

# 強制実行（確認なし）
php artisan subscription:reset-monthly --force
```

## ログ確認

- Laravelログ: `storage/logs/laravel.log`
- 専用ログ（設定した場合）: `storage/logs/subscription-reset.log`

## 注意事項

1. **タイムゾーン**: サーバーのタイムゾーンが日本時間（Asia/Tokyo）に設定されていることを確認
2. **重複実行防止**: コマンドは同じ日に複数回実行しても、条件に合致しない限りリセットされません
3. **履歴保存**: リセット時の利用状況はログに記録されます