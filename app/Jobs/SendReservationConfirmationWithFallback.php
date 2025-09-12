<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Services\ReservationConfirmationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendReservationConfirmationWithFallback implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * The reservation instance.
     */
    public $reservation;
    
    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;
    
    /**
     * The maximum number of seconds the job should be allowed to run.
     */
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(Reservation $reservation)
    {
        $this->reservation = $reservation;
    }

    /**
     * Execute the job.
     */
    public function handle(ReservationConfirmationService $confirmationService): void
    {
        Log::info('reservation_confirmation_job_start', [
            'reservation_id' => $this->reservation->id,
            'customer_id' => $this->reservation->customer_id,
            'attempt' => $this->attempts()
        ]);
        
        // 1. 既に送信済みチェック
        if ($this->reservation->confirmation_sent_at) {
            Log::info('reservation_confirmation_skip', [
                'reservation_id' => $this->reservation->id,
                'reason' => 'already_sent',
                'sent_at' => $this->reservation->confirmation_sent_at
            ]);
            return;
        }
        
        // 2. 予約がキャンセルされていないかチェック
        $this->reservation->refresh();
        if (in_array($this->reservation->status, ['cancelled', 'canceled'])) {
            Log::info('reservation_confirmation_skip', [
                'reservation_id' => $this->reservation->id,
                'reason' => 'reservation_cancelled',
                'status' => $this->reservation->status
            ]);
            return;
        }
        
        // 3. LINE連携チェック
        $customer = $this->reservation->customer;
        if ($customer->line_user_id) {
            Log::info('reservation_confirmation_try_line', [
                'reservation_id' => $this->reservation->id,
                'customer_id' => $customer->id,
                'line_user_id' => $customer->line_user_id
            ]);
            
            // LINE送信試行
            if ($confirmationService->sendLineConfirmation($this->reservation)) {
                $confirmationService->markConfirmationSent($this->reservation, 'line');
                return;
            }
            
            // LINE送信失敗時はSMSフォールバック
            Log::info('reservation_confirmation_line_failed_fallback_to_sms', [
                'reservation_id' => $this->reservation->id,
                'customer_id' => $customer->id
            ]);
        }
        
        // 4. SMS送信（静穏時間チェック付き）
        if ($confirmationService->isQuietHours()) {
            Log::info('reservation_confirmation_quiet_hours_reschedule', [
                'reservation_id' => $this->reservation->id,
                'current_time' => now()->toISOString(),
                'quiet_start' => config('reservation.quiet_hours_start'),
                'quiet_end' => config('reservation.quiet_hours_end')
            ]);
            
            // 翌朝の営業開始時間に再スケジュール
            $delaySeconds = $confirmationService->getDelayUntilNextBusinessHours();
            $this->release($delaySeconds);
            return;
        }
        
        Log::info('reservation_confirmation_try_sms', [
            'reservation_id' => $this->reservation->id,
            'customer_id' => $customer->id,
            'phone' => $customer->phone
        ]);
        
        if ($confirmationService->sendSmsConfirmation($this->reservation)) {
            $confirmationService->markConfirmationSent($this->reservation, 'sms');
        } else {
            // SMS送信も失敗した場合
            Log::error('reservation_confirmation_all_methods_failed', [
                'reservation_id' => $this->reservation->id,
                'customer_id' => $customer->id,
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries
            ]);
            
            // 最大試行回数に達していない場合は再試行
            if ($this->attempts() < $this->tries) {
                $this->release(300); // 5分後に再試行
            }
        }
    }
    
    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('reservation_confirmation_job_failed', [
            'reservation_id' => $this->reservation->id,
            'customer_id' => $this->reservation->customer_id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
