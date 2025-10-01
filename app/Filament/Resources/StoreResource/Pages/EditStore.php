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

                    // 管理者ユーザーでLINE連携済みのユーザーを取得
                    $adminUsers = \App\Models\User::whereIn('role', ['superadmin', 'admin'])
                        ->whereNotNull('line_user_id')
                        ->get();

                    if ($adminUsers->isEmpty()) {
                        Notification::make()
                            ->title('送信対象がいません')
                            ->body('LINE連携済みの管理者ユーザーがいません。管理者アカウントでLINE連携を行ってください。')
                            ->warning()
                            ->send();
                        return;
                    }

                    $lineService = new SimpleLineService();
                    $successCount = 0;
                    $failCount = 0;

                    foreach ($adminUsers as $user) {
                        // line_user_idが空の場合はスキップ
                        if (empty($user->line_user_id)) {
                            continue;
                        }

                        $message = "【LINE接続テスト】\n\n";
                        $message .= "店舗: {$store->name}\n";
                        $message .= "送信日時: " . now()->format('Y-m-d H:i:s') . "\n\n";
                        $message .= "✅ LINE連携が正常に動作しています！";

                        if ($lineService->sendMessage($store, $user->line_user_id, $message)) {
                            $successCount++;
                        } else {
                            $failCount++;
                        }
                    }

                    if ($successCount > 0) {
                        Notification::make()
                            ->title('テスト送信成功')
                            ->body("管理者 {$successCount}人にテストメッセージを送信しました。LINEを確認してください。")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('テスト送信失敗')
                            ->body('テストメッセージの送信に失敗しました。Channel Access Tokenを確認してください。')
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