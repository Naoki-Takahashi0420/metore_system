<?php

namespace App\Observers;

use App\Models\Reservation;
use App\Models\MedicalRecord;
use Carbon\Carbon;

class ReservationObserver
{
    /**
     * Handle the Reservation "created" event.
     */
    public function created(Reservation $reservation): void
    {
        $this->linkToMedicalRecord($reservation);
    }

    /**
     * Handle the Reservation "updated" event.
     */
    public function updated(Reservation $reservation): void
    {
        //
    }

    /**
     * Handle the Reservation "deleted" event.
     */
    public function deleted(Reservation $reservation): void
    {
        //
    }

    /**
     * Handle the Reservation "restored" event.
     */
    public function restored(Reservation $reservation): void
    {
        //
    }

    /**
     * Handle the Reservation "force deleted" event.
     */
    public function forceDeleted(Reservation $reservation): void
    {
        //
    }

    /**
     * 予約をカルテの次回来院予定日と関連付け
     */
    private function linkToMedicalRecord(Reservation $reservation): void
    {
        // この顧客の最新のカルテで、次回来院予定日が設定されているものを取得
        $latestMedicalRecord = MedicalRecord::where('customer_id', $reservation->customer_id)
            ->whereNotNull('next_visit_date')
            ->where('reservation_status', 'pending') // まだ予約されていないもの
            ->orderBy('record_date', 'desc')
            ->first();

        if (!$latestMedicalRecord) {
            return;
        }

        // 予約日と推奨日の差異を計算
        $reservationDate = Carbon::parse($reservation->reservation_date);
        $recommendedDate = Carbon::parse($latestMedicalRecord->next_visit_date);
        $daysDifference = $reservationDate->diffInDays($recommendedDate, false);

        // カルテを更新
        $latestMedicalRecord->update([
            'actual_reservation_date' => $reservation->reservation_date,
            'date_difference_days' => $daysDifference,
            'reservation_status' => 'booked',
        ]);

        // ログ出力（デバッグ用）
        \Log::info('Reservation linked to medical record', [
            'reservation_id' => $reservation->id,
            'medical_record_id' => $latestMedicalRecord->id,
            'customer_id' => $reservation->customer_id,
            'recommended_date' => $latestMedicalRecord->next_visit_date,
            'actual_date' => $reservation->reservation_date,
            'days_difference' => $daysDifference,
        ]);
    }
}
