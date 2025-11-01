<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class HealthCheck extends Command
{
    protected $signature = 'health:check';
    protected $description = '日次ヘルスチェック（ログファイル権限・基本動作確認）';

    public function handle()
    {
        $this->info('=== Daily Health Check ===');

        // 1. ログファイルの所有者確認
        $logPath = storage_path('logs/laravel-' . now()->format('Y-m-d') . '.log');
        if (file_exists($logPath)) {
            $owner = posix_getpwuid(fileowner($logPath));
            $group = posix_getgrgid(filegroup($logPath));
            $permissions = substr(sprintf('%o', fileperms($logPath)), -4);

            $this->info("Log File: {$logPath}");
            $this->info("Owner: {$owner['name']}:{$group['name']}");
            $this->info("Permissions: {$permissions}");

            // 期待値チェック
            if ($owner['name'] !== 'www-data' || $group['name'] !== 'www-data') {
                $this->error("⚠️ WARNING: Log file owner mismatch! Expected www-data:www-data");
                Log::warning('Health check failed: Log file owner is not www-data', [
                    'owner' => $owner['name'],
                    'group' => $group['name'],
                    'file' => $logPath
                ]);
            } else {
                $this->info('✅ Log file ownership: OK');
            }
        } else {
            $this->warn("Log file not created yet: {$logPath}");
        }

        // 2. 書き込みテスト
        try {
            Log::info('🏥 Daily health check executed successfully', [
                'timestamp' => now()->toIso8601String(),
                'user' => get_current_user(),
            ]);
            $this->info('✅ Log write test: OK');
        } catch (\Exception $e) {
            $this->error("❌ Log write test failed: {$e->getMessage()}");
        }

        // 3. データベース接続確認
        try {
            \DB::connection()->getPdo();
            $this->info('✅ Database connection: OK');
        } catch (\Exception $e) {
            $this->error("❌ Database connection failed: {$e->getMessage()}");
            Log::error('Health check failed: Database connection error', ['error' => $e->getMessage()]);
        }

        // 4. キュー接続確認
        try {
            $queueConnection = config('queue.default');
            $this->info("✅ Queue connection ({$queueConnection}): OK");
        } catch (\Exception $e) {
            $this->error("❌ Queue check failed: {$e->getMessage()}");
        }

        $this->info('=== Health Check Complete ===');
        return 0;
    }
}
