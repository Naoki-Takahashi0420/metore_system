# AKIBA店 顧客インポート手順書

## 概要

AKIBA店（store_id: 4）の顧客データ167人分をインポートします。

## ファイル情報

- **元データ**: `2025.9.22AKIBA末広町店顧客.txt` (UTF-16LE, 168行)
- **本番用CSV**: `2025.9.22AKIBA末広町店顧客_for_production.csv` (UTF-8, 168行)
- **データ件数**: 167人分（ヘッダー除く）

## 修正内容

以下の5行について、重複を避けるためにお客様番号とメールアドレスをクリアしました：

- 行15: かな さん（お客様番号13を削除）
- 行19: 渋下寛人 さん（お客様番号13を削除）
- 行20: あつこ さん（お客様番号14を削除）
- 行121: 宮下真由美 さん（メールアドレス削除）
- 行124: 田原航平 さん（メールアドレス削除）

## インポート手順（本番環境）

### 1. CSVファイルを本番サーバーにアップロード

```bash
scp -i ~/.ssh/xsyumeno-ssh-key.pem \
  2025.9.22AKIBA末広町店顧客_for_production.csv \
  ubuntu@54.64.54.226:/tmp/
```

### 2. SSH接続

```bash
ssh -i ~/.ssh/xsyumeno-ssh-key.pem ubuntu@54.64.54.226
```

### 3. ファイルを配置

```bash
sudo cp /tmp/2025.9.22AKIBA末広町店顧客_for_production.csv /var/www/html/
sudo chown www-data:www-data /var/www/html/2025.9.22AKIBA末広町店顧客_for_production.csv
```

### 4. インポートコマンドを作成

```bash
sudo tee /var/www/html/app/Console/Commands/ImportAkibaCustomers.php > /dev/null << 'EOF'
<?php

namespace App\Console\Commands;

use App\Services\CustomerImportService;
use Illuminate\Console\Command;

class ImportAkibaCustomers extends Command
{
    protected $signature = 'import:akiba-customers';
    protected $description = 'AKIBA店の顧客データをインポート（167人分）';

    public function handle(CustomerImportService $importService)
    {
        $filePath = base_path('2025.9.22AKIBA末広町店顧客_for_production.csv');
        $storeId = 4; // AKIBA店

        if (!file_exists($filePath)) {
            $this->error('ファイルが見つかりません: ' . $filePath);
            return 1;
        }

        $this->info('AKIBA店の顧客データをインポートしています...');
        $this->info('ファイル: ' . $filePath);
        $this->info('店舗ID: ' . $storeId);

        $results = $importService->import($filePath, $storeId);

        $this->newLine();
        $this->info('=== インポート結果 ===');
        $this->info('成功: ' . $results['success_count'] . '件');
        $this->info('スキップ: ' . $results['skip_count'] . '件');
        $this->info('エラー: ' . $results['error_count'] . '件');

        if (!empty($results['errors'])) {
            $this->newLine();
            $this->warn('=== エラー詳細 ===');
            foreach ($results['errors'] as $error) {
                $this->line('行' . $error['row'] . ': ' . $error['message']);
            }
        }

        return 0;
    }
}
EOF
```

### 5. インポート実行

```bash
cd /var/www/html
sudo php artisan import:akiba-customers
```

### 6. 結果確認

```bash
sudo php artisan tinker --execute="echo 'AKIBA店顧客数: ' . \App\Models\Customer::where('store_id', 4)->count();"
```

## 期待される結果

- **既存顧客**: スキップされる
- **新規顧客**: 追加される
- **最終顧客数**: 約160-167人（既存データにより変動）

## トラブルシューティング

### エラーが発生した場合

1. ログを確認
```bash
sudo tail -100 /var/www/html/storage/logs/laravel.log
```

2. データベースを確認
```bash
sudo php artisan tinker --execute="
\$duplicates = \App\Models\Customer::where('store_id', 4)
  ->select('phone', \DB::raw('COUNT(*) as count'))
  ->whereNotNull('phone')
  ->groupBy('phone')
  ->having('count', '>', 1)
  ->get();
print_r(\$duplicates->toArray());
"
```

## 注意事項

- インポート前に必ずデータベースのバックアップを取得してください
- 既存顧客は自動的にスキップされます
- 電話番号やメールアドレスが重複する場合はスキップされます
