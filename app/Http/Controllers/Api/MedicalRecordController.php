<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use Illuminate\Http\JsonResponse;

class MedicalRecordController extends Controller
{
    /**
     * 予約IDからカルテの存在をチェック
     */
    public function checkByReservation(int $reservationId): JsonResponse
    {
        $medicalRecord = MedicalRecord::where('reservation_id', $reservationId)->first();

        return response()->json([
            'exists' => $medicalRecord !== null,
            'medical_record_id' => $medicalRecord?->id,
        ]);
    }
}
