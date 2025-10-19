<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SystemLogs extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.system-logs';

    protected static ?string $navigationLabel = 'ログ';

    protected static ?string $title = 'システムログ';

    protected static ?int $navigationSort = 100;

    // ログ設定（必要に応じてconfig/logging.phpに移動可能）
    protected const MAX_FILE_SIZE_MB = 50; // 最大ファイルサイズ（MB）
    protected const MAX_LINES_TO_READ = 100000; // ファイルから読み込む最大行数
    protected const MAX_LOGS_TO_DISPLAY = 500; // 画面に表示する最大ログ数

    public $logs = [];
    public $filter = 'all'; // all, reservation, email, auth, error
    public $selectedLogs = [];
    public $selectAll = false;
    public $debugInfo = null; // デバッグ情報

    /**
     * スーパーアドミンのみアクセス可能
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        // スーパーアドミンロールを持っているか確認
        try {
            return $user && $user->hasRole('super_admin');
        } catch (\Exception $e) {
            // ロールシステムが使えない場合は is_admin で判定
            return $user && $user->is_admin;
        }
    }

    public function mount(): void
    {
        try {
            $this->loadLogs();
        } catch (\Exception $e) {
            \Log::error('SystemLogs mount error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->logs = [];
        }
    }

    public function loadLogs(): void
    {
        try {
            // 最新のログファイルを取得（ローテーション対応）
            $logPath = $this->getLatestLogFile();

            if (!$logPath || !File::exists($logPath)) {
                $this->logs = [];
                return;
            }

            // ファイルサイズチェック
            $fileSize = File::size($logPath);
            $maxSize = self::MAX_FILE_SIZE_MB * 1024 * 1024;

            // デバッグ情報を更新
            if ($this->debugInfo) {
                $this->debugInfo['file_size_bytes'] = $fileSize;
                $this->debugInfo['file_size_mb'] = round($fileSize / 1024 / 1024, 2);
                $this->debugInfo['file_readable'] = is_readable($logPath);
            }

            if ($fileSize > $maxSize) {
                $fileSizeMB = round($fileSize / 1024 / 1024, 2);
                $this->logs = [[
                    'timestamp' => date('Y-m-d H:i:s'),
                    'content' => sprintf('ログファイルが大きすぎます（%s MB / %s MB）。最新の行のみ読み込みます。', $fileSizeMB, self::MAX_FILE_SIZE_MB),
                    'type' => 'error',
                    'level' => 'warning',
                    'five_w_one_h' => [
                        'who' => null,
                        'what' => 'ファイルサイズ超過',
                        'when' => null,
                        'where' => null,
                        'why' => sprintf('ログファイルが%s MBを超えています（現在: %s MB）', self::MAX_FILE_SIZE_MB, $fileSizeMB),
                        'how' => null,
                    ]
                ]];

                // ファイルが大きい場合は tail で最新の行のみ読み込む
                $command = sprintf('tail -n %d %s', self::MAX_LINES_TO_READ, escapeshellarg($logPath));
                $logContent = shell_exec($command);
                $logLines = explode("\n", $logContent);
            } else {
                $logContent = File::get($logPath);
                $logLines = explode("\n", $logContent);

                // 最新N行のみ処理（パフォーマンス対策）
                $logLines = array_slice($logLines, -self::MAX_LINES_TO_READ);
            }
        } catch (\Exception $e) {
            \Log::error('SystemLogs loadLogs error', [
                'error' => $e->getMessage()
            ]);
            $this->logs = [[
                'timestamp' => date('Y-m-d H:i:s'),
                'content' => 'ログ読み込みエラー: ' . $e->getMessage(),
                'type' => 'error',
                'level' => 'error',
                'five_w_one_h' => [
                    'who' => null,
                    'what' => 'ログ読み込みエラー',
                    'when' => null,
                    'where' => null,
                    'why' => $e->getMessage(),
                    'how' => null,
                ]
            ]];
            return;
        }

        $parsedLogs = [];
        $currentLog = null;

        foreach ($logLines as $line) {
            // 新しいログエントリの開始を検出
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                // 前のログを保存
                if ($currentLog !== null) {
                    $parsedLogs[] = $currentLog;
                }

                // 新しいログを開始
                $currentLog = [
                    'timestamp' => $matches[1],
                    'content' => $line,
                    'type' => $this->detectLogType($line),
                    'level' => $this->detectLogLevel($line),
                ];
            } elseif ($currentLog !== null) {
                // 複数行のログを結合
                $currentLog['content'] .= "\n" . $line;

                // タイプが未定の場合は再判定
                if ($currentLog['type'] === 'other') {
                    $currentLog['type'] = $this->detectLogType($currentLog['content']);
                }
            }
        }

        // 最後のログを保存
        if ($currentLog !== null) {
            $parsedLogs[] = $currentLog;
        }

        // デバッグ情報を更新
        if ($this->debugInfo) {
            $this->debugInfo['total_lines_read'] = count($logLines);
            $this->debugInfo['parsed_logs_before_filter'] = count($parsedLogs);
        }

        // 重要なログのみフィルタリング（otherも含める）
        $parsedLogs = array_filter($parsedLogs, function ($log) {
            return in_array($log['type'], ['reservation', 'email', 'auth', 'error', 'admin_notification', 'other']);
        });

        // デバッグ情報を更新
        if ($this->debugInfo) {
            $this->debugInfo['logs_after_type_filter'] = count($parsedLogs);
        }

        // 新しい順にソート
        $parsedLogs = array_reverse($parsedLogs);

        // フィルタ適用
        if ($this->filter !== 'all') {
            $parsedLogs = array_filter($parsedLogs, function ($log) {
                return $log['type'] === $this->filter;
            });
        }

        // デバッグ情報を更新
        if ($this->debugInfo) {
            $this->debugInfo['current_filter'] = $this->filter;
            $this->debugInfo['logs_after_user_filter'] = count($parsedLogs);
        }

        // 各ログに5W1H情報を追加
        $parsedLogs = array_map(function ($log) {
            $log['five_w_one_h'] = $this->extract5W1H($log['content']);
            return $log;
        }, $parsedLogs);

        // 最新N件のみ表示
        $this->logs = array_slice($parsedLogs, 0, self::MAX_LOGS_TO_DISPLAY);

        // デバッグ情報を更新
        if ($this->debugInfo) {
            $this->debugInfo['final_logs_count'] = count($this->logs);
        }
    }

    /**
     * 最新のログファイルを取得（ローテーション対応）
     */
    private function getLatestLogFile(): ?string
    {
        $logDir = storage_path('logs');

        // デバッグ情報を収集
        $debug = [
            'log_dir' => $logDir,
            'dir_exists' => is_dir($logDir),
        ];

        // laravel.logが存在し、かつサイズが0より大きければそれを使用
        $singleLog = $logDir . '/laravel.log';
        $debug['single_log_exists'] = File::exists($singleLog);

        if (File::exists($singleLog) && File::size($singleLog) > 0) {
            $debug['using_file'] = 'laravel.log';
            $this->debugInfo = $debug;
            return $singleLog;
        }

        // ローテーションモード：laravel-YYYY-MM-DD.logを探す
        $logFiles = File::glob($logDir . '/laravel-*.log');
        $debug['rotation_files_found'] = count($logFiles);
        $debug['rotation_files'] = array_map('basename', $logFiles);

        if (empty($logFiles)) {
            // すべてのログファイルを確認
            $allLogFiles = File::glob($logDir . '/*.log');
            $debug['all_log_files'] = array_map('basename', $allLogFiles);
            $this->debugInfo = $debug;
            return null;
        }

        // 最新のファイルを取得（ファイル名でソート）
        rsort($logFiles);
        $debug['selected_file'] = basename($logFiles[0]);
        $debug['using_file'] = basename($logFiles[0]);
        $this->debugInfo = $debug;

        return $logFiles[0];
    }

    /**
     * 5W1H情報を抽出
     */
    private function extract5W1H(string $content): array
    {
        return [
            'who' => $this->extractWho($content),
            'what' => $this->extractWhat($content),
            'when' => null, // タイムスタンプで表示済み
            'where' => $this->extractWhere($content),
            'why' => $this->extractWhy($content),
            'how' => $this->extractHow($content),
        ];
    }

    private function extractWho(string $content): ?string
    {
        // メール送信先（to）- より優先的に抽出
        if (preg_match('/"to"\s*[:=]\s*"([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})"/i', $content, $matches)) {
            return "送信先: {$matches[1]}";
        }

        // ユーザーID
        if (preg_match('/user_id["\']?\s*[:=]\s*["\']?(\d+)/i', $content, $matches)) {
            return "ユーザーID: {$matches[1]}";
        }

        // メールアドレス
        if (preg_match('/email["\']?\s*[:=]\s*["\']?([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $content, $matches)) {
            return "メール: {$matches[1]}";
        }

        // 顧客ID
        if (preg_match('/customer_id["\']?\s*[:=]\s*["\']?(\d+)/i', $content, $matches)) {
            return "顧客ID: {$matches[1]}";
        }

        return null;
    }

    private function extractWhat(string $content): ?string
    {
        // SESメール送信成功（MessageID付き）
        if (Str::contains($content, 'メール送信成功') && preg_match('/messageId["\']?\s*[:=]\s*["\']?([a-zA-Z0-9\-@\.]+)/i', $content, $matches)) {
            return "メール送信完了 (SES ID: " . substr($matches[1], 0, 20) . "...)";
        }

        // 予約ID
        if (preg_match('/reservation_id["\']?\s*[:=]\s*["\']?(\d+)/i', $content, $matches)) {
            return "予約ID: {$matches[1]}";
        }

        // メール件名
        if (preg_match('/subject["\']?\s*[:=]\s*["\']?([^"\'}\n]+)/i', $content, $matches)) {
            return "件名: " . trim($matches[1]);
        }

        // イベントタイプ
        if (preg_match('/type["\']?\s*[:=]\s*["\']?([a-z_]+)/i', $content, $matches)) {
            return "タイプ: {$matches[1]}";
        }

        // メッセージから主要アクションを抽出
        if (Str::contains($content, '予約作成')) {
            return "予約作成";
        }
        if (Str::contains($content, 'Sending email')) {
            return "メール送信準備";
        }
        if (Str::contains($content, 'メール送信成功')) {
            return "メール送信完了";
        }
        if (Str::contains($content, 'Admin notification')) {
            return "管理者通知";
        }
        if (Str::contains($content, 'ログイン')) {
            return "ログイン";
        }

        return null;
    }

    private function extractWhere(string $content): ?string
    {
        // 店舗ID
        if (preg_match('/store_id["\']?\s*[:=]\s*["\']?(\d+)/i', $content, $matches)) {
            return "店舗ID: {$matches[1]}";
        }

        // ソース（予約経路）
        if (preg_match('/source["\']?\s*[:=]\s*["\']?([a-z_]+)/i', $content, $matches)) {
            $sourceLabel = match($matches[1]) {
                'admin' => 'ダッシュボード',
                'online' => 'オンライン予約',
                'phone' => '電話予約',
                'walk_in' => '来店予約',
                'mypage' => 'マイページ',
                default => $matches[1]
            };
            return "経路: {$sourceLabel}";
        }

        return null;
    }

    private function extractWhy(string $content): ?string
    {
        // ステータス
        if (preg_match('/status["\']?\s*[:=]\s*["\']?([a-z_]+)/i', $content, $matches)) {
            return "ステータス: {$matches[1]}";
        }

        // 結論
        if (preg_match('/conclusion["\']?\s*[:=]\s*["\']?([a-z_]+)/i', $content, $matches)) {
            $conclusionLabel = match($matches[1]) {
                'success' => '成功',
                'failure' => '失敗',
                'cancelled' => 'キャンセル',
                default => $matches[1]
            };
            return "結果: {$conclusionLabel}";
        }

        // エラーメッセージ
        if (Str::contains($content, ['ERROR', 'Exception', 'Failed'])) {
            if (preg_match('/Exception:\s*(.+?)(\n|$)/i', $content, $matches)) {
                return "エラー: " . Str::limit(trim($matches[1]), 50);
            }
            return "エラーが発生しました";
        }

        return null;
    }

    private function extractHow(string $content): ?string
    {
        // SESメール送信成功
        if (Str::contains($content, 'メール送信成功') && Str::contains($content, 'messageId')) {
            return "経路: AWS SES（送信成功）";
        }

        // メソッド
        if (preg_match('/method["\']?\s*[:=]\s*["\']?([A-Z]+)/i', $content, $matches)) {
            return "メソッド: {$matches[1]}";
        }

        // チャネル（通知経路）
        if (Str::contains($content, 'LINE')) {
            return "経路: LINE";
        }
        if (Str::contains($content, 'SMS')) {
            return "経路: SMS";
        }
        if (Str::contains($content, ['Email', 'メール'])) {
            return "経路: メール";
        }

        // メール通知が有効/無効
        if (preg_match('/email_enabled["\']?\s*[:=]\s*([a-z]+)/i', $content, $matches)) {
            $enabled = $matches[1] === 'true' ? '有効' : '無効';
            return "メール通知: {$enabled}";
        }

        return null;
    }

    private function detectLogType(string $content): string
    {
        // 除外：Email notification check（これはデバッグログなので other に分類）
        if (Str::contains($content, 'Email notification check')) {
            return 'other';
        }

        // 予約関連
        if (Str::contains($content, ['Reservation created', '予約作成', 'ReservationCreated'])) {
            return 'reservation';
        }

        // メール送信関連（実際の送信のみ）
        if (Str::contains($content, ['📧', 'Sending email', 'sendEmail', 'Mail sent', 'メール送信成功'])) {
            return 'email';
        }

        // 管理者通知
        if (Str::contains($content, ['Admin notification sent', '🔍 [DEBUG] getStoreAdmins', 'Admin notification'])) {
            return 'admin_notification';
        }

        // 認証関連
        if (Str::contains($content, ['two-factor', '2FA', 'authentication', 'login', 'logout', 'ログイン', 'セッション'])) {
            return 'auth';
        }

        // エラー
        if (Str::contains($content, ['ERROR', 'Exception', 'Failed', 'Error', 'エラー'])) {
            return 'error';
        }

        return 'other';
    }

    private function detectLogLevel(string $content): string
    {
        if (Str::contains($content, ['.ERROR:', 'ERROR'])) {
            return 'error';
        }

        if (Str::contains($content, ['.WARNING:', 'WARNING', '⚠️'])) {
            return 'warning';
        }

        if (Str::contains($content, ['.INFO:', 'INFO', '✅', '📧', '🔍'])) {
            return 'info';
        }

        if (Str::contains($content, ['.DEBUG:', 'DEBUG'])) {
            return 'debug';
        }

        return 'info';
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->loadLogs();
    }

    public function refreshLogs(): void
    {
        $this->loadLogs();
        $this->selectedLogs = [];
        $this->selectAll = false;
    }

    public function clearAllLogs(): void
    {
        $logPath = $this->getLatestLogFile();

        if ($logPath && File::exists($logPath)) {
            File::put($logPath, '');
            $this->loadLogs();
            $this->selectedLogs = [];
            $this->selectAll = false;
        }
    }

    public function clearOldLogs(): void
    {
        $logPath = $this->getLatestLogFile();

        if (!$logPath || !File::exists($logPath)) {
            return;
        }

        $logContent = File::get($logPath);
        $logLines = explode("\n", $logContent);

        $sevenDaysAgo = now()->subDays(7);
        $newLines = [];

        foreach ($logLines as $line) {
            // ログのタイムスタンプを抽出
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                $logDate = Carbon::parse($matches[1]);

                // 7日以内のログのみ保持
                if ($logDate->isAfter($sevenDaysAgo)) {
                    $newLines[] = $line;
                }
            } else {
                // タイムスタンプがない行は前の行の続きなので保持
                if (!empty($newLines)) {
                    $newLines[] = $line;
                }
            }
        }

        File::put($logPath, implode("\n", $newLines));
        $this->loadLogs();
        $this->selectedLogs = [];
        $this->selectAll = false;
    }

    public function toggleSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedLogs = range(0, count($this->logs) - 1);
        } else {
            $this->selectedLogs = [];
        }
    }

    public function deleteSelected(): void
    {
        if (empty($this->selectedLogs)) {
            return;
        }

        $logPath = $this->getLatestLogFile();

        if (!$logPath || !File::exists($logPath)) {
            return;
        }

        $logContent = File::get($logPath);
        $logLines = explode("\n", $logContent);

        // 選択されたログのタイムスタンプを取得
        $timestampsToDelete = [];
        foreach ($this->selectedLogs as $index) {
            if (isset($this->logs[$index]['timestamp'])) {
                $timestampsToDelete[] = $this->logs[$index]['timestamp'];
            }
        }

        // タイムスタンプが一致する行を除外
        $newLines = [];
        $skipUntilNextLog = false;

        foreach ($logLines as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                $timestamp = $matches[1];

                if (in_array($timestamp, $timestampsToDelete)) {
                    $skipUntilNextLog = true;
                    continue;
                } else {
                    $skipUntilNextLog = false;
                    $newLines[] = $line;
                }
            } elseif (!$skipUntilNextLog) {
                $newLines[] = $line;
            }
        }

        File::put($logPath, implode("\n", $newLines));
        $this->loadLogs();
        $this->selectedLogs = [];
        $this->selectAll = false;
    }
}
