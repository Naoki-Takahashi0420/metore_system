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
        $customer = $reservation->customer;
        if (!$customer) {
            return;
        }

        $countChanged = false; // カウントが変更されたかのフラグ

        // ステータスが変更された場合、顧客のカウントを更新
        if ($reservation->isDirty('status')) {
            $oldStatus = $reservation->getOriginal('status');
            $newStatus = $reservation->status;

            // cancel_reason をチェック（カウント対象外かどうか）
            $cancelReason = $reservation->cancel_reason;
            $shouldExclude = \App\Models\Customer::shouldExcludeFromCount($cancelReason);

            // キャンセルに変更された場合
            if ($oldStatus !== 'cancelled' && $newStatus === 'cancelled') {
                if (!$shouldExclude) {
                    $customer->increment('cancellation_count');
                    $customer->update(['last_cancelled_at' => now()]);
                    $countChanged = true;

                    \Log::info('[ReservationObserver] Cancellation count incremented', [
                        'customer_id' => $customer->id,
                        'reservation_id' => $reservation->id,
                        'cancel_reason' => $cancelReason,
                        'new_count' => $customer->cancellation_count,
                    ]);
                } else {
                    \Log::info('[ReservationObserver] Cancellation excluded from count', [
                        'customer_id' => $customer->id,
                        'reservation_id' => $reservation->id,
                        'cancel_reason' => $cancelReason,
                        'reason' => 'Store fault or system fix',
                    ]);
                }
            }
            // キャンセルから他のステータスに戻された場合
            elseif ($oldStatus === 'cancelled' && $newStatus !== 'cancelled') {
                if (!$shouldExclude) {
                    // 0未満にならないようガード
                    if ($customer->cancellation_count > 0) {
                        $customer->decrement('cancellation_count');
                        $countChanged = true;

                        \Log::info('[ReservationObserver] Cancellation count decremented', [
                            'customer_id' => $customer->id,
                            'reservation_id' => $reservation->id,
                            'new_count' => $customer->cancellation_count,
                        ]);
                    }
                }
            }

            // 来店なしに変更された場合
            if ($oldStatus !== 'no_show' && $newStatus === 'no_show') {
                if (!$shouldExclude) {
                    $customer->increment('no_show_count');
                    $countChanged = true;

                    \Log::info('[ReservationObserver] No-show count incremented', [
                        'customer_id' => $customer->id,
                        'reservation_id' => $reservation->id,
                        'cancel_reason' => $cancelReason,
                        'new_count' => $customer->no_show_count,
                    ]);
                } else {
                    \Log::info('[ReservationObserver] No-show excluded from count', [
                        'customer_id' => $customer->id,
                        'reservation_id' => $reservation->id,
                        'cancel_reason' => $cancelReason,
                    ]);
                }
            }
            // 来店なしから他のステータスに戻された場合
            elseif ($oldStatus === 'no_show' && $newStatus !== 'no_show') {
                if (!$shouldExclude) {
                    // 0未満にならないようガード
                    if ($customer->no_show_count > 0) {
                        $customer->decrement('no_show_count');
                        $countChanged = true;

                        \Log::info('[ReservationObserver] No-show count decremented', [
                            'customer_id' => $customer->id,
                            'reservation_id' => $reservation->id,
                            'new_count' => $customer->no_show_count,
                        ]);
                    }
                }
            }
        }

        // 予約日時が変更された場合（ステータスがキャンセル以外）
        if ($reservation->status !== 'cancelled' &&
            ($reservation->isDirty('reservation_date') || $reservation->isDirty('start_time'))) {
            $oldDate = $reservation->getOriginal('reservation_date');
            $oldTime = $reservation->getOriginal('start_time');
            $newDate = $reservation->reservation_date;
            $newTime = $reservation->start_time;

            // 実際に日時が変更されているかチェック
            if ($oldDate !== $newDate || $oldTime !== $newTime) {
                $customer->increment('change_count');
                $countChanged = true;

                \Log::info('[ReservationObserver] Change count incremented', [
                    'customer_id' => $customer->id,
                    'reservation_id' => $reservation->id,
                    'old_date' => $oldDate,
                    'new_date' => $newDate,
                    'new_count' => $customer->change_count,
                ]);
            }
        }

        // カウントが変更された場合、自動リスク判定を実行
        if ($countChanged) {
            $customer->refresh(); // 最新のカウントを取得
            $customer->evaluateRiskStatus();
        }
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
