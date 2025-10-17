<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SystemLogs extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string $view = 'filament.pages.system-logs';

    protected static ?string $navigationLabel = 'システムログ';

    protected static ?string $title = 'システムログ';

    protected static ?int $navigationSort = 100;

    public $logs = [];
    public $filter = 'all'; // all, reservation, email, auth, error

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
        $this->loadLogs();
    }

    public function loadLogs(): void
    {
        $logPath = storage_path('logs/laravel.log');

        if (!File::exists($logPath)) {
            $this->logs = [];
            return;
        }

        $logContent = File::get($logPath);
        $logLines = explode("\n", $logContent);

        // 最新500行のみ処理（パフォーマンス対策）
        $logLines = array_slice($logLines, -500);

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

        // 重要なログのみフィルタリング
        $parsedLogs = array_filter($parsedLogs, function ($log) {
            return in_array($log['type'], ['reservation', 'email', 'auth', 'error', 'admin_notification']);
        });

        // 新しい順にソート
        $parsedLogs = array_reverse($parsedLogs);

        // フィルタ適用
        if ($this->filter !== 'all') {
            $parsedLogs = array_filter($parsedLogs, function ($log) {
                return $log['type'] === $this->filter;
            });
        }

        // 各ログに5W1H情報を追加
        $parsedLogs = array_map(function ($log) {
            $log['five_w_one_h'] = $this->extract5W1H($log['content']);
            return $log;
        }, $parsedLogs);

        // 最新100件のみ表示
        $this->logs = array_slice($parsedLogs, 0, 100);
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
            return "メール送信";
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
        // 予約関連
        if (Str::contains($content, ['Reservation created', '予約作成', 'ReservationCreated'])) {
            return 'reservation';
        }

        // メール送信関連
        if (Str::contains($content, ['📧', 'Sending email', 'Email notification', 'sendEmail'])) {
            return 'email';
        }

        // 管理者通知
        if (Str::contains($content, ['Admin notification sent', '🔍 [DEBUG] getStoreAdmins'])) {
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
        $this->dispatch('notify', [
            'message' => 'ログを更新しました',
            'type' => 'success'
        ]);
    }

    public function clearLogs(): void
    {
        $logPath = storage_path('logs/laravel.log');

        if (File::exists($logPath)) {
            File::put($logPath, '');
            $this->loadLogs();
            $this->dispatch('notify', [
                'message' => 'ログをクリアしました',
                'type' => 'success'
            ]);
        }
    }
}
