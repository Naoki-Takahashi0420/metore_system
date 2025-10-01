<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Resources\StoreResource;
use App\Services\SimpleLineService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditStore extends EditRecord
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('test_line')
                ->label('LINE接続テスト')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('LINE接続テスト')
                ->modalDescription('管理者にテストメッセージを送信します。LINEで友だち追加済みの管理者ユーザーにのみ送信されます。')
                ->modalSubmitActionLabel('テスト送信')
                ->action(function () {
                    $store = $this->record;

                    // LINE設定チェック
                    if (!$store->line_enabled) {
                        Notification::make()
                            ->title('LINE連携が無効です')
                            ->body('店舗設定でLINE連携を有効にしてください。')
                            ->warning()
                            ->send();
                        return;
                    }

                    if (!$store->line_channel_access_token) {
                        Notification::make()
                            ->title('Channel Access Tokenが未設定です')
                            ->body('店舗設定でChannel Access Tokenを入力してください。')
                            ->warning()
                            ->send();
                        return;
                    }

                    // テスト用の固定User ID（開発者アカウント）
                    $testLineUserId = 'Uc37e9137beadca4a6d5c04aaada19ab1';

                    $lineService = new SimpleLineService();

                    $message = "【LINE接続テスト】\n\n";
                    $message .= "店舗: {$store->name}\n";
                    $message .= "送信日時: " . now()->format('Y-m-d H:i:s') . "\n\n";
                    $message .= "✅ LINE連携が正常に動作しています！\n\n";
                    $message .= "テスト送信先: 開発者アカウント";

                    // 送信前にログをクリアしてから送信
                    \Log::info('LINE接続テスト開始', [
                        'store_id' => $store->id,
                        'store_name' => $store->name,
                        'test_user_id' => $testLineUserId,
                        'has_token' => !empty($store->line_channel_access_token),
                        'token_length' => strlen($store->line_channel_access_token ?? ''),
                    ]);

                    if ($lineService->sendMessage($store, $testLineUserId, $message)) {
                        Notification::make()
                            ->title('テスト送信成功')
                            ->body('開発者アカウントにテストメッセージを送信しました。LINEを確認してください。')
                            ->success()
                            ->send();
                    } else {
                        // 最後のログエントリを取得
                        $logFile = storage_path('logs/laravel.log');
                        $lastLines = [];
                        if (file_exists($logFile)) {
                            $lines = file($logFile);
                            $lastLines = array_slice($lines, -50);
                            $errorInfo = '';
                            foreach (array_reverse($lastLines) as $line) {
                                if (strpos($line, 'LINE送信失敗') !== false || strpos($line, 'response_body') !== false) {
                                    $errorInfo = $line;
                                    break;
                                }
                            }
                        }

                        Notification::make()
                            ->title('テスト送信失敗')
                            ->body('ログを確認してください: ' . ($errorInfo ? substr($errorInfo, 0, 200) : 'ログが見つかりません'))
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn () => $this->record && $this->record->line_enabled),

            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}