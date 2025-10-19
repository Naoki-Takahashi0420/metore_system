<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // 既存のmanageable_storesをロード
        $data['manageable_stores'] = $this->record->manageableStores()->pluck('stores.id')->toArray();

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;

        // 既存の管理店舗関係をクリア
        $record->manageableStores()->detach();

        // オーナーのみ管理可能店舗の関係を再保存
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

        // 既存のstore_managersエントリーを確認
        $existingEntry = \DB::table('store_managers')
            ->where('store_id', $user->store_id)
            ->where('user_id', $user->id)
            ->first();

        if ($emailEnabled) {
            // 通知ONの場合: store_managersテーブルに追加（既存の場合は何もしない）
            if (!$existingEntry) {
                \DB::table('store_managers')->insert([
                    'store_id' => $user->store_id,
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                \Log::info('User added to store_managers', [
                    'user_id' => $user->id,
                    'store_id' => $user->store_id,
                    'email' => $user->email,
                ]);
            }
        } else {
            // 通知OFFの場合: store_managersテーブルから削除
            if ($existingEntry) {
                \DB::table('store_managers')
                    ->where('store_id', $user->store_id)
                    ->where('user_id', $user->id)
                    ->delete();

                \Log::info('User removed from store_managers', [
                    'user_id' => $user->id,
                    'store_id' => $user->store_id,
                    'email' => $user->email,
                ]);
            }
        }
    }
}
