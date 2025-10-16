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
        $filePath = base_path('2025.9.22AKIBA末広町店顧客_fixed.txt');
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
