<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ClaudeHelpService
{
    private ?string $apiKey = null;
    private string $model = 'claude-3-5-sonnet-20241022';
    private int $maxTokens = 1024;

    public function __construct()
    {
        $this->loadSettings();
    }

    /**
     * 設定を読み込み
     */
    private function loadSettings(): void
    {
        // DBから設定を取得（キャッシュ付き）
        $settings = Cache::remember('claude_settings', 300, function () {
            if (!DB::getSchemaBuilder()->hasTable('settings')) {
                return [];
            }
            return DB::table('settings')
                ->where('key', 'like', 'claude.%')
                ->pluck('value', 'key')
                ->toArray();
        });

        $this->apiKey = $settings['claude.api_key'] ?? config('claude.api_key');
        $this->model = config('claude.model');
        $this->maxTokens = config('claude.max_tokens');
    }

    /**
     * チャット応答を取得
     */
    public function chat(string $userMessage, array $conversationHistory = [], array $context = []): array
    {
        // 機能が無効の場合
        if (!$this->isEnabled()) {
            return [
                'response' => 'ヘルプチャット機能は現在無効です。管理者にお問い合わせください。',
                'usage' => []
            ];
        }

        // APIキーが未設定の場合
        if (empty($this->apiKey)) {
            return [
                'response' => 'Claude APIキーが設定されていません。管理者に設定を依頼してください。',
                'usage' => []
            ];
        }

        // マニュアル全文を取得
        $manualContent = $this->loadManuals();

        // システムプロンプトを構築
        $systemPrompt = $this->buildSystemPrompt($manualContent, $context);

        // メッセージ配列を構築
        $messages = $this->buildMessages($userMessage, $conversationHistory);

        try {
            $response = Http::timeout(30)->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'system' => [
                    [
                        'type' => 'text',
                        'text' => $systemPrompt,
                        'cache_control' => ['type' => 'ephemeral']  // Prompt Caching
                    ]
                ],
                'messages' => $messages,
            ]);

            if ($response->failed()) {
                Log::error('Claude API Error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return [
                    'response' => 'エラーが発生しました。しばらくしてから再度お試しください。',
                    'usage' => []
                ];
            }

            $data = $response->json();

            // 使用状況をログ
            Log::info('Claude API Usage', [
                'user_id' => auth()->id(),
                'input_tokens' => $data['usage']['input_tokens'] ?? 0,
                'output_tokens' => $data['usage']['output_tokens'] ?? 0,
                'cache_read_tokens' => $data['usage']['cache_read_input_tokens'] ?? 0,
                'cache_creation_tokens' => $data['usage']['cache_creation_input_tokens'] ?? 0,
            ]);

            return [
                'response' => $data['content'][0]['text'] ?? 'エラーが発生しました',
                'usage' => $data['usage'] ?? []
            ];

        } catch (\Exception $e) {
            Log::error('Claude Help Service Error', [
                'message' => $e->getMessage(),
                'user_message' => $userMessage,
                'user_id' => auth()->id()
            ]);

            return [
                'response' => '申し訳ございません。一時的にサポートチャットが利用できません。しばらくしてから再度お試しください。',
                'usage' => []
            ];
        }
    }

    /**
     * システムプロンプトを構築
     */
    private function buildSystemPrompt(string $manualContent, array $context = []): string
    {
        $contextSection = '';
        $pageName = '不明';

        if (!empty($context)) {
            $pageName = $context['page_name'] ?? '不明';
            $contextSection = "\n\n【現在のユーザー状況】\n";
            $contextSection .= "- 閲覧中のページ: {$context['page_name']}\n";
            $contextSection .= "- ユーザー権限: {$context['role']}\n";

            if (isset($context['store_name'])) {
                $contextSection .= "- 店舗: {$context['store_name']}\n";
            }

            $contextSection .= "\n↑この情報を考慮して、今見ている画面に関連する回答を優先してください。\n";
        }

        return <<<PROMPT
あなたは「目のトレーニング予約管理システム」の専門サポートアシスタントです。
{$contextSection}

【重要なルール】
1. ユーザーが今見ている画面（{$pageName}）に関連する説明を優先する
2. 以下のマニュアル内容のみを参照して回答してください
3. マニュアルに記載がない内容は「マニュアルに記載がありません。管理者にお問い合わせください」と正直に答える
4. 推測や想像で回答しない
5. URLやリンクはマニュアルに記載されているものだけを案内
6. システムへの操作は一切行わず、説明のみ提供
7. 回答は簡潔に、箇条書きを活用して分かりやすく
8. 専門用語は避け、初心者でも理解できる言葉で説明

【対応範囲】
- 予約の作成・変更・キャンセル方法
- 顧客情報の登録・編集
- カルテの記入方法
- サブスク・チケットの管理
- シフトの設定
- 通知設定（LINE/SMS/メール）
- レポートの見方

【マニュアル】
{$manualContent}

PROMPT;
    }

    /**
     * メッセージ配列を構築
     */
    private function buildMessages(string $userMessage, array $history): array
    {
        $messages = [];

        // 会話履歴を追加（最新10件のみ）
        foreach (array_slice($history, -10) as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }

        // 現在のユーザーメッセージ
        $messages[] = [
            'role' => 'user',
            'content' => $userMessage
        ];

        return $messages;
    }

    /**
     * マニュアル全文を読み込み
     */
    private function loadManuals(): string
    {
        // キャッシュから取得
        $cacheDuration = config('claude.cache_duration', 3600);

        return Cache::remember('help_manual_content', $cacheDuration, function () {
            $manualPath = config('claude.manual_path');
            $content = "# システムマニュアル\n\n";

            // docs/manual/ ディレクトリが存在しない場合
            if (!File::exists($manualPath)) {
                return $content . "マニュアルはまだ作成されていません。";
            }

            // 全Markdownファイルを結合
            $files = File::glob($manualPath . '/*.md');
            sort($files);

            if (empty($files)) {
                return $content . "マニュアルファイルが見つかりません。";
            }

            foreach ($files as $file) {
                $content .= File::get($file) . "\n\n---\n\n";
            }

            return $content;
        });
    }

    /**
     * レート制限チェック
     */
    public function checkRateLimit(int $userId): bool
    {
        $dailyLimit = $this->getSetting('claude.daily_limit_per_user', config('claude.rate_limit.daily_per_user'));

        $key = "help_chat_limit_user_{$userId}_" . date('Y-m-d');
        $count = Cache::get($key, 0);

        if ($count >= $dailyLimit) {
            return false;
        }

        Cache::put($key, $count + 1, now()->endOfDay());
        return true;
    }

    /**
     * 機能が有効かチェック
     */
    public function isEnabled(): bool
    {
        $enabled = $this->getSetting('claude.enabled', config('claude.enabled'));
        return $enabled === '1' || $enabled === true;
    }

    /**
     * 設定値を取得
     */
    private function getSetting(string $key, $default = null)
    {
        $settings = Cache::get('claude_settings', []);
        return $settings[$key] ?? $default;
    }

    /**
     * 接続テスト
     */
    public function testConnection(?string $apiKey = null): array
    {
        $testApiKey = $apiKey ?? $this->apiKey;

        if (empty($testApiKey)) {
            return [
                'success' => false,
                'error' => 'APIキーが設定されていません'
            ];
        }

        try {
            $response = Http::timeout(10)->withHeaders([
                'x-api-key' => $testApiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 50,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'テスト'
                    ]
                ]
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => '接続成功'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'API接続エラー: ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
