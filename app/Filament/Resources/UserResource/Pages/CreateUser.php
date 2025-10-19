<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;

        // オーナーのみ管理可能店舗の関係を保存
        if ($record->hasRole('owner') && isset($this->data['manageable_stores'])) {
            $storeIds = $this->data['manageable_stores'];

            foreach ($storeIds as $storeId) {
                $record->manageableStores()->attach($storeId, ['role' => 'owner']);
            }
        }

        // 通知設定とstore_managersテーブルを連動
        $this->syncStoreManagers($record);
    }

    /**
     * 通知設定に応じてstore_managersテーブルを更新
     */
    protected function syncStoreManagers($user): void
    {
        // store_idが設定されていない場合はスキップ
        if (!$user->store_id) {
            return;
        }

        $emailEnabled = $user->notification_preferences['email_enabled'] ?? true;

        // 通知ONの場合のみstore_managersテーブルに追加
        if ($emailEnabled) {
            // 既存のエントリーを確認
            $existingEntry = \DB::table('store_managers')
                ->where('store_id', $user->store_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$existingEntry) {
                \DB::table('store_managers')->insert([
                    'store_id' => $user->store_id,
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                \Log::info('User added to store_managers on creation', [
                    'user_id' => $user->id,
                    'store_id' => $user->store_id,
                    'email' => $user->email,
                ]);
            }
        }
    }
}
