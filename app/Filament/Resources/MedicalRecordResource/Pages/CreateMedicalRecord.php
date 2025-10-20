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
    
    protected function getFormModel(): string 
    {
        return static::$resource::getModel();
    }
    
    protected function fillForm(): void
    {
        $data = [];

        // URLパラメータから顧客IDと予約IDを取得して自動設定
        $customerId = request()->query('customer_id');
        $reservationId = request()->query('reservation_id');

        if ($customerId) {
            $data['customer_id'] = (int) $customerId;
        }

        if ($reservationId) {
            $data['reservation_id'] = (int) $reservationId;

            // 予約情報から自動設定
            $reservation = Reservation::with(['customer', 'store', 'staff'])->find($reservationId);
            if ($reservation) {
                // 施術日を予約日に設定 - Carbon::parseで文字列を日付オブジェクトに変換
                $data['treatment_date'] = \Carbon\Carbon::parse($reservation->reservation_date)->format('Y-m-d');

                // 店舗IDを予約から取得
                $data['store_id'] = $reservation->store_id;

                // 顧客IDが未設定の場合は予約から取得
                if (!isset($data['customer_id'])) {
                    $data['customer_id'] = $reservation->customer_id;
                }

                // 担当スタッフがいる場合は対応者に設定
                if ($reservation->staff_id && $reservation->staff) {
                    $data['handled_by'] = $reservation->staff->name;
                }
            }
        }

        // 顧客の前回のカルテから変わらない情報（顧客管理情報）を引き継ぐ
        $finalCustomerId = $data['customer_id'] ?? null;
        if ($finalCustomerId) {
            $latestRecord = \App\Models\MedicalRecord::where('customer_id', $finalCustomerId)
                ->orderBy('treatment_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($latestRecord) {
                // 変わらない情報を引き継ぐ（既に設定されていない場合のみ）
                $data['payment_method'] = $data['payment_method'] ?? $latestRecord->payment_method;
                $data['reservation_source'] = $data['reservation_source'] ?? $latestRecord->reservation_source;
                $data['visit_purpose'] = $data['visit_purpose'] ?? $latestRecord->visit_purpose;
                $data['genetic_possibility'] = $data['genetic_possibility'] ?? $latestRecord->genetic_possibility;
                $data['has_astigmatism'] = $data['has_astigmatism'] ?? $latestRecord->has_astigmatism;
                $data['eye_diseases'] = $data['eye_diseases'] ?? $latestRecord->eye_diseases;
                $data['workplace_address'] = $data['workplace_address'] ?? $latestRecord->workplace_address;
                $data['device_usage'] = $data['device_usage'] ?? $latestRecord->device_usage;
            }
        }

        $this->form->fill($data);
    }
    
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
        // カルテ作成後は一覧ページに戻る
        return $this->getResource()::getUrl('index');
    }
}
