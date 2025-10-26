<?php

namespace App\Filament\Resources\MedicalRecordResource\Pages;

use App\Filament\Resources\MedicalRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMedicalRecord extends EditRecord
{
    protected static string $resource = MedicalRecordResource::class;

    protected static bool $shouldCheckUnsavedChanges = true;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // vision_recordsの各項目に自動で回数を設定
        if (isset($data['vision_records']) && is_array($data['vision_records'])) {
            foreach ($data['vision_records'] as $index => &$record) {
                $record['session'] = $index + 1;
            }

            // session_numberを最新の回数に設定
            $data['session_number'] = count($data['vision_records']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // 老眼詳細測定データの保存
        $data = $this->form->getState();

        if (isset($data['presbyopiaMeasurements'])) {
            // 既存のデータを削除
            $this->record->presbyopiaMeasurements()->delete();

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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // 予約から店舗情報を取得してフォームに表示（表示のみ、DBには保存しない）
        if ($this->record->reservation_id) {
            $reservation = \App\Models\Reservation::find($this->record->reservation_id);
            if ($reservation && $reservation->store_id) {
                $data['store_id'] = $reservation->store_id;
            }
        }

        // 既存の老眼詳細測定データを読み込む
        $before = $this->record->presbyopiaMeasurements()->where('status', '施術前')->first();
        $after = $this->record->presbyopiaMeasurements()->where('status', '施術後')->first();

        if ($before) {
            $data['presbyopiaMeasurements']['before'] = [
                'a_95_left' => $before->a_95_left,
                'a_95_right' => $before->a_95_right,
                'b_50_left' => $before->b_50_left,
                'b_50_right' => $before->b_50_right,
                'c_25_left' => $before->c_25_left,
                'c_25_right' => $before->c_25_right,
                'd_12_left' => $before->d_12_left,
                'd_12_right' => $before->d_12_right,
                'e_6_left' => $before->e_6_left,
                'e_6_right' => $before->e_6_right,
            ];
        }

        if ($after) {
            $data['presbyopiaMeasurements']['after'] = [
                'a_95_left' => $after->a_95_left,
                'a_95_right' => $after->a_95_right,
                'b_50_left' => $after->b_50_left,
                'b_50_right' => $after->b_50_right,
                'c_25_left' => $after->c_25_left,
                'c_25_right' => $after->c_25_right,
                'd_12_left' => $after->d_12_left,
                'd_12_right' => $after->d_12_right,
                'e_6_left' => $after->e_6_left,
                'e_6_right' => $after->e_6_right,
            ];
        }

        return $data;
    }
}
