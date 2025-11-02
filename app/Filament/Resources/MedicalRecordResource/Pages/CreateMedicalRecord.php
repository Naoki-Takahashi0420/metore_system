<?php

namespace App\Filament\Resources\MedicalRecordResource\Pages;

use App\Filament\Resources\MedicalRecordResource;
use App\Models\Reservation;
use App\Models\Customer;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMedicalRecord extends CreateRecord
{
    protected static string $resource = MedicalRecordResource::class;

    protected static bool $shouldCheckUnsavedChanges = true;

    protected function getFormModel(): string
    {
        return static::$resource::getModel();
    }

    // 顧客管理情報の引き継ぎは各フィールドの default() で実装済み
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // vision_recordsの各項目に自動で回数を設定
        if (isset($data['vision_records']) && is_array($data['vision_records'])) {
            foreach ($data['vision_records'] as $index => &$record) {
                $record['session'] = $index + 1;
            }

            // session_numberを最新の回数に設定
            $data['session_number'] = count($data['vision_records']);
        }

        // staff_idとcreated_byを現在のユーザーに設定
        $data['staff_id'] = auth()->id();
        $data['created_by'] = auth()->id();

        // 店舗IDが未設定の場合、ユーザーの所属店舗を強制設定（最終防衛ライン）
        if (empty($data['store_id'])) {
            $user = auth()->user();
            if ($user && $user->store_id) {
                $data['store_id'] = $user->store_id;
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // 老眼詳細測定データの保存
        $data = $this->form->getState();

        if (isset($data['presbyopiaMeasurements'])) {
            // 施術前のデータ
            if (isset($data['presbyopiaMeasurements']['before'])) {
                $beforeData = $data['presbyopiaMeasurements']['before'];
                if ($this->hasAnyValue($beforeData)) {
                    $this->record->presbyopiaMeasurements()->create(array_merge(
                        ['status' => '施術前'],
                        $beforeData
                    ));
                }
            }

            // 施術後のデータ
            if (isset($data['presbyopiaMeasurements']['after'])) {
                $afterData = $data['presbyopiaMeasurements']['after'];
                if ($this->hasAnyValue($afterData)) {
                    $this->record->presbyopiaMeasurements()->create(array_merge(
                        ['status' => '施術後'],
                        $afterData
                    ));
                }
            }
        }
    }

    private function hasAnyValue(array $data): bool
    {
        foreach ($data as $value) {
            if (!empty($value)) {
                return true;
            }
        }
        return false;
    }

    protected function getRedirectUrl(): string
    {
        // カルテ作成後は編集ページに遷移（一覧ページはカルテ数が多くタイムアウトするため）
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
