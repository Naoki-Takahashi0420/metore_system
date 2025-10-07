<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\ClaudeHelpService;

class HelpChatModal extends Component
{
    public bool $isOpen = false;
    public string $message = '';
    public array $conversationHistory = [];
    public bool $isLoading = false;
    public ?string $errorMessage = null;

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
        $context = [
            'page_name' => $this->getCurrentPageName(),
            'role' => auth()->user()->roles->pluck('name')->first() ?? 'staff',
            'store_name' => auth()->user()->store?->name ?? null,
            'url' => url()->current(),
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
        $this->open(); // 初回メッセージを表示
    }

    private function getCurrentPageName(): string
    {
        $url = url()->current();
        $route = request()->route()?->getName() ?? '';

        return match(true) {
            str_contains($route, 'customers') || str_contains($url, 'customers') => '顧客管理',
            str_contains($route, 'reservations') || str_contains($url, 'reservations') => '予約管理',
            str_contains($route, 'medical-records') || str_contains($url, 'medical-records') => 'カルテ管理',
            str_contains($route, 'menu') || str_contains($url, 'menu') => 'メニュー管理',
            str_contains($route, 'shift') || str_contains($url, 'shift') => 'シフト管理',
            str_contains($route, 'subscriptions') || str_contains($url, 'subscriptions') => 'サブスク管理',
            str_contains($route, 'tickets') || str_contains($url, 'tickets') => 'チケット管理',
            str_contains($route, 'dashboard') || str_contains($url, 'dashboard') => 'ダッシュボード',
            default => '管理画面',
        };
    }

    public function render()
    {
        return view('livewire.help-chat-modal');
    }
}
