<?php

namespace App\Filament\Pages;

use App\Models\LineSetting;
use App\Services\SimpleLineService;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class LineSettings extends Page implements HasForms
{
    use InteractsWithForms;
    
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left';
    protected static ?string $navigationLabel = 'LINE設定';
    protected static ?string $navigationGroup = 'システム設定';
    protected static ?int $navigationSort = 10;
    protected static string $view = 'filament.pages.line-settings';
    
    public ?array $data = [];
    public string $promotionMessage = '';
    
    public function mount(): void
    {
        $settings = LineSetting::getSettings();
        $this->form->fill($settings->toArray());
    }
    
    protected function getFormSchema(): array
    {
        return [
            // ① 予約確認
            Forms\Components\Section::make('① 予約確認メッセージ')
                ->description('予約完了時に自動送信されます')
                ->schema([
                    Forms\Components\Toggle::make('send_confirmation')
                        ->label('予約確認を送信する'),
                        
                    Forms\Components\Textarea::make('message_confirmation')
                        ->label('メッセージ内容')
                        ->rows(5)
                        ->helperText($this->getVariableHelp()),
                ])
                ->collapsible(),
                
            // ② リマインダー
            Forms\Components\Section::make('② リマインダーメッセージ')
                ->description('予約前に自動送信されます')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Toggle::make('send_reminder_24h')
                                ->label('24時間前に送信'),
                            Forms\Components\Toggle::make('send_reminder_3h')
                                ->label('3時間前に送信'),
                        ]),
                        
                    Forms\Components\Textarea::make('message_reminder_24h')
                        ->label('24時間前のメッセージ')
                        ->rows(4),
                        
                    Forms\Components\Textarea::make('message_reminder_3h')
                        ->label('3時間前のメッセージ')
                        ->rows(4),
                ])
                ->collapsible(),
                
            // ③ 初回客フォロー
            Forms\Components\Section::make('③ 初回客フォローメッセージ')
                ->description('初回来店後、次の予約がない顧客に自動送信')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Toggle::make('send_follow_30d')
                                ->label('30日後に送信'),
                            Forms\Components\Toggle::make('send_follow_60d')
                                ->label('60日後に送信'),
                        ]),
                        
                    Forms\Components\Textarea::make('message_follow_30d')
                        ->label('30日後のメッセージ')
                        ->rows(4),
                        
                    Forms\Components\Textarea::make('message_follow_60d')
                        ->label('60日後のメッセージ')
                        ->rows(4),
                ])
                ->collapsible(),
        ];
    }
    
    public function save(): void
    {
        $data = $this->form->getState();
        $settings = LineSetting::getSettings();
        $settings->update($data);
        
        Notification::make()
            ->title('設定を保存しました')
            ->success()
            ->send();
    }
    
    public function sendPromotion(): void
    {
        if (empty($this->promotionMessage)) {
            Notification::make()
                ->title('メッセージを入力してください')
                ->warning()
                ->send();
            return;
        }
        
        $service = new SimpleLineService();
        $result = $service->sendPromotion($this->promotionMessage);
        
        Notification::make()
            ->title('プロモーション送信完了')
            ->body("成功: {$result['success']}件 / 失敗: {$result['failed']}件 / 合計: {$result['total']}件")
            ->success()
            ->send();
            
        $this->promotionMessage = '';
    }
    
    private function getVariableHelp(): HtmlString
    {
        return new HtmlString('
            <div class="text-xs space-y-1">
                <div>使用可能な変数:</div>
                <div class="flex flex-wrap gap-2">
                    <code class="bg-gray-100 px-1 rounded">{{customer_name}}</code>
                    <code class="bg-gray-100 px-1 rounded">{{reservation_date}}</code>
                    <code class="bg-gray-100 px-1 rounded">{{reservation_time}}</code>
                    <code class="bg-gray-100 px-1 rounded">{{store_name}}</code>
                    <code class="bg-gray-100 px-1 rounded">{{menu_name}}</code>
                </div>
            </div>
        ');
    }
}