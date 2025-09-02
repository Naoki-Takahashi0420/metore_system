<?php

namespace App\Filament\Resources\MedicalRecordResource\Pages;

use App\Filament\Resources\MedicalRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMedicalRecord extends CreateRecord
{
    protected static string $resource = MedicalRecordResource::class;
    
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
