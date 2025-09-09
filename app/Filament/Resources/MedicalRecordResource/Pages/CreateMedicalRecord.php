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
}
