<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\ClaudeHelpService;
use App\Models\HelpChatLog;

class HelpChatModal extends Component
{
    public bool $isOpen = false;
    public string $message = '';
    public array $conversationHistory = [];
    public bool $isLoading = false;
    public ?string $errorMessage = null;
    public ?int $currentLogId = null; // 最新のログID
    public bool $showFeedbackForm = false;
    public string $feedbackMessage = '';

    protected $listeners = ['open-help-chat' => 'open'];

    public function open(): void
    {
        $this->isOpen = true;
        $this->errorMessage = null;

        // 初回メッセージ
        if (empty($this->conversationHistory)) {
            $this->conversationHistory[] = [
                'role' => 'assistant',
                'content' => "こんにちは！システムの使い方をサポートします。\n\n何かお困りですか？お気軽にご質問ください。"
            ];
        }
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function sendMessage(): void
    {
        if (empty(trim($this->message))) {
            return;
        }

        $this->errorMessage = null;

        // レート制限チェック
        $service = app(ClaudeHelpService::class);
        if (!$service->checkRateLimit(auth()->id())) {
            $this->errorMessage = '1日の利用制限に達しました。明日再度お試しください。';
            return;
        }

        // ユーザーメッセージを履歴に追加
        $userMessage = $this->message;
        $this->conversationHistory[] = [
            'role' => 'user',
            'content' => $userMessage
        ];

        $this->message = '';
        $this->isLoading = true;

        // コンテキスト情報を収集
        $user = auth()->user();

        $context = [
            'page_name' => $this->getCurrentPageName(),
            'url' => url()->current(),
            'route' => request()->route()?->getName() ?? null,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'role' => $user->roles->pluck('name')->first() ?? 'staff',
            'store_id' => $user->store_id ?? null,
            'store_name' => $user->store?->name ?? null,
            'browser' => request()->header('User-Agent'),
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ];

        try {
            // Claude API呼び出し
            $result = $service->chat(
                $userMessage,
                $this->conversationHistory,
                $context
            );

            // 応答を履歴に追加
            $this->conversationHistory[] = [
                'role' => 'assistant',
                'content' => $result['response']
            ];

            // データベースに保存
            $log = HelpChatLog::create([
                'user_id' => auth()->id(),
                'page_name' => $context['page_name'] ?? '不明',
                'question' => $userMessage,
                'answer' => $result['response'],
                'context' => $context,
                'usage' => $result['usage'] ?? null,
            ]);

            $this->currentLogId = $log->id;

        } catch (\Exception $e) {
            $this->errorMessage = 'エラーが発生しました: ' . $e->getMessage();

            // エラーメッセージを履歴に追加
            $this->conversationHistory[] = [
                'role' => 'assistant',
                'content' => '申し訳ございません。エラーが発生しました。しばらくしてから再度お試しください。'
            ];
        } finally {
            $this->isLoading = false;
        }
    }

    public function clearHistory(): void
    {
        $this->conversationHistory = [];
        $this->errorMessage = null;
        $this->currentLogId = null;
        $this->showFeedbackForm = false;
        $this->feedbackMessage = '';
        $this->open(); // 初回メッセージを表示
    }

    public function markResolved(): void
    {
        if ($this->currentLogId) {
            HelpChatLog::find($this->currentLogId)->update([
                'is_resolved' => true,
            ]);
            $this->showFeedbackForm = false;
            $this->currentLogId = null;
        }
    }

    public function showFeedback(): void
    {
        $this->showFeedbackForm = true;
    }

    public function submitFeedback(): void
    {
        if ($this->currentLogId && !empty(trim($this->feedbackMessage))) {
            HelpChatLog::find($this->currentLogId)->update([
                'is_resolved' => false,
                'feedback' => $this->feedbackMessage,
            ]);
            $this->showFeedbackForm = false;
            $this->feedbackMessage = '';
            $this->currentLogId = null;

            // 成功メッセージを履歴に追加
            $this->conversationHistory[] = [
                'role' => 'assistant',
                'content' => 'フィードバックありがとうございます。管理者に報告されました。'
            ];
        }
    }

    public function cancelFeedback(): void
    {
        $this->showFeedbackForm = false;
        $this->feedbackMessage = '';
    }

    private function getCurrentPageName(): string
    {
        $url = url()->current();
        $route = request()->route()?->getName() ?? '';

        return match(true) {
            str_contains($route, 'customers') || str_contains($url, 'customers') => '顧客管理',
            str_contains($route, 'reservations') || str_contains($url, 'reservations') => '予約管理',
            str_contains($route, 'medical-records') || str_contains($url, 'medical-records') => 'カルテ管理',
            str_contains($route, 'menu-categories') || str_contains($url, 'menu-categories') => 'メニューカテゴリ管理',
            str_contains($route, 'menus') || str_contains($url, 'menus') => 'メニュー管理',
            str_contains($route, 'shift') || str_contains($url, 'shift') => 'シフト管理',
            str_contains($route, 'subscriptions') || str_contains($url, 'subscriptions') => 'サブスク管理',
            str_contains($route, 'tickets') || str_contains($url, 'tickets') => 'チケット管理',
            str_contains($route, 'stores') || str_contains($url, 'stores') => '店舗管理',
            str_contains($route, 'users') || str_contains($url, 'users') => 'ユーザー管理',
            str_contains($route, 'settings') || str_contains($url, 'settings') => '設定',
            str_contains($route, 'help-chat-logs') || str_contains($url, 'help-chat-logs') => 'ヘルプ質問履歴',
            str_contains($route, 'dashboard') || str_contains($url, 'dashboard') => 'ダッシュボード',
            default => '管理画面',
        };
    }

    public function render()
    {
        return view('livewire.help-chat-modal');
    }
}
