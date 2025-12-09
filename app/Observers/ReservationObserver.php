<?php

namespace App\Observers;

use App\Models\Reservation;
use App\Models\MedicalRecord;
use App\Events\ReservationChanged;
use App\Events\ReservationCancelled;
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
                // キャンセル通知イベントを発火（顧客への通知用）
                ReservationCancelled::dispatch($reservation);
                \Log::info('[ReservationObserver] ReservationCancelled event dispatched', [
                    'reservation_id' => $reservation->id,
                    'customer_id' => $customer->id,
                ]);

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
                
                // ========== 5日ルール再チェックを追加（2025-11-27修正） ==========
                // 日付が変更された場合、5日ルールをチェック
                if ($oldDate !== $newDate) {
                    // 顧客の5日ルール除外設定をチェック
                    if (!$customer->ignore_interval_rule) {
                        $store = $reservation->store;
                        $minIntervalDays = $store->min_interval_days ?? 5;
                        
                        // 他の予約との間隔をチェック
                        $existingReservations = Reservation::where('customer_id', $reservation->customer_id)
                            ->whereNotIn('status', ['cancelled', 'canceled'])
                            ->where('store_id', $reservation->store_id)
                            ->where('id', '!=', $reservation->id) // 自身を除外
                            ->get();
                        
                        $targetDateTime = Carbon::parse($newDate);
                        
                        foreach ($existingReservations as $otherReservation) {
                            $otherDate = Carbon::parse($otherReservation->reservation_date);
                            $daysDiff = abs($targetDateTime->diffInDays($otherDate));
                            
                            if ($daysDiff > 0 && $daysDiff <= $minIntervalDays) {
                                \Log::error('⚠️ 予約日変更が5日ルール違反', [
                                    'reservation_id' => $reservation->id,
                                    'customer_id' => $reservation->customer_id,
                                    'old_date' => $oldDate,
                                    'new_date' => $newDate,
                                    'conflict_with' => $otherReservation->id,
                                    'conflict_date' => $otherReservation->reservation_date,
                                    'days_diff' => $daysDiff,
                                    'min_interval_days' => $minIntervalDays
                                ]);
                                
                                // エラーを投げて変更を阻止
                                throw new \Exception(
                                    sprintf('予約日の変更により、%sの予約と%d日以内となるため変更できません。（最小間隔: %d日）',
                                        $otherReservation->reservation_date,
                                        $daysDiff,
                                        $minIntervalDays
                                    )
                                );
                            }
                        }
                        
                        \Log::info('✅ 予約日変更の5日ルールチェック完了（問題なし）', [
                            'reservation_id' => $reservation->id,
                            'old_date' => $oldDate,
                            'new_date' => $newDate,
                            'checked_reservations' => $existingReservations->count()
                        ]);
                    }
                }
                // ========== 5日ルール再チェック終了 ==========
                
                // 変更通知イベントを発火（顧客への通知用）
                $oldReservationData = [
                    'id' => $reservation->id,
                    'reservation_date' => $oldDate,
                    'start_time' => $oldTime,
                    'menu_id' => $reservation->getOriginal('menu_id'),
                    'total_amount' => $reservation->getOriginal('total_amount'),
                ];
                ReservationChanged::dispatch($oldReservationData, $reservation);
                \Log::info('[ReservationObserver] ReservationChanged event dispatched', [
                    'reservation_id' => $reservation->id,
                    'customer_id' => $customer->id,
                    'old_date' => $oldDate,
                    'new_date' => $newDate,
                    'old_time' => $oldTime,
                    'new_time' => $newTime,
                ]);

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
