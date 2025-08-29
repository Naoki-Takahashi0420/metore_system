<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use App\Services\LineMessageService;

class SimpleLINE extends Page implements HasForms
{
    use InteractsWithForms;
    
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left';
    protected static ?string $navigationLabel = 'LINE設定（シンプル版）';
    protected static ?string $navigationGroup = 'LINE管理';
    protected static ?int $navigationSort = -1;
    protected static string $view = 'filament.pages.simple-line';
    
    // フォームデータ
    public $send_confirmation = true;
    public $reminder_24h = true;
    public $reminder_3h = true;
    public $follow_30days = true;
    public $follow_60days = true;
    public $promotion_message = '';
    
    public function mount(): void
    {
        // 設定を読み込む（実際はDBから）
        $this->form->fill([
            'send_confirmation' => true,
            'reminder_24h' => true,
            'reminder_3h' => true,
            'follow_30days' => true,
            'follow_60days' => true,
        ]);
    }
    
    public function save(): void
    {
        // 設定を保存（実際はDBに）
        Notification::make()
            ->title('設定を保存しました')
            ->success()
            ->send();
    }
    
    public function sendPromotion(): void
    {
        if (!$this->promotion_message) {
            Notification::make()
                ->title('メッセージを入力してください')
                ->warning()
                ->send();
            return;
        }
        
        // プロモーション送信処理
        $service = new LineMessageService();
        $result = $service->sendPromotion($this->promotion_message);
        
        Notification::make()
            ->title('送信完了')
            ->body("全{$result['total']}件中、{$result['success']}件送信成功")
            ->success()
            ->send();
            
        $this->promotion_message = '';
    }
}