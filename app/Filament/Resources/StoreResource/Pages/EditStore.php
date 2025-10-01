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
                        // デバッグ情報を含めたエラー表示
                        $tokenPreview = substr($store->line_channel_access_token, 0, 20) . '...' . substr($store->line_channel_access_token, -10);

                        $debugInfo = "【デバッグ情報 - すべての設定】\n\n";
                        $debugInfo .= "■ 店舗情報\n";
                        $debugInfo .= "Store ID: {$store->id}\n";
                        $debugInfo .= "Store Name: {$store->name}\n";
                        $debugInfo .= "Store Code: {$store->code}\n\n";

                        $debugInfo .= "■ LINE設定\n";
                        $debugInfo .= "LINE有効: " . ($store->line_enabled ? 'はい' : 'いいえ') . "\n";
                        $debugInfo .= "Channel ID: " . ($store->line_channel_id ?: '未設定') . "\n";
                        $debugInfo .= "Channel Secret: " . ($store->line_channel_secret ? substr($store->line_channel_secret, 0, 10) . '...' . substr($store->line_channel_secret, -5) : '未設定') . "\n";
                        $debugInfo .= "Access Token: {$tokenPreview}\n";
                        $debugInfo .= "Token Length: " . strlen($store->line_channel_access_token) . "\n";
                        $debugInfo .= "LIFF ID: " . ($store->line_liff_id ?: '未設定') . "\n\n";

                        $debugInfo .= "■ LINE通知設定\n";
                        $debugInfo .= "予約確認送信: " . ($store->line_send_reservation_confirmation ? '有効' : '無効') . "\n";
                        $debugInfo .= "リマインダー送信: " . ($store->line_send_reminder ? '有効' : '無効') . "\n";
                        $debugInfo .= "フォローアップ送信: " . ($store->line_send_followup ? '有効' : '無効') . "\n";
                        $debugInfo .= "プロモーション送信: " . ($store->line_send_promotion ? '有効' : '無効') . "\n\n";

                        $debugInfo .= "■ テスト情報\n";
                        $debugInfo .= "Test User ID: {$testLineUserId}\n";
                        $debugInfo .= "Webhook URL: " . config('app.url') . '/api/line/webhook/' . $store->code;

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
                            ->body($debugInfo . "\n\n" . '【エラーログ】' . "\n" . ($errorInfo ? substr($errorInfo, 0, 300) : 'ログが見つかりません'))
                            ->danger()
                            ->duration(30000) // 30秒表示
                            ->send();
                    }
                })
                ->visible(fn () => $this->record && $this->record->line_enabled),

            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}