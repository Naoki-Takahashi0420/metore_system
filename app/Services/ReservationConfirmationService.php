<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReservationConfirmationService
{
    protected $lineMessageService;
    protected $smsService;
    
    public function __construct(
        LineMessageService $lineMessageService,
        SmsService $smsService
    ) {
        $this->lineMessageService = $lineMessageService;
        $this->smsService = $smsService;
    }
    
    /**
     * LINE確認通知を送信
     *
     * @param Reservation $reservation
     * @return bool
     */
    public function sendLineConfirmation(Reservation $reservation): bool
    {
        try {
            $customer = $reservation->customer;
            
            if (!$customer->line_user_id) {
                Log::info('reservation_confirmation_skip', [
                    'reservation_id' => $reservation->id,
                    'reason' => 'no_line_user_id'
                ]);
                return false;
            }
            
            $success = $this->lineMessageService->sendReservationConfirmation($reservation);
            
            if ($success) {
                $reservation->update([
                    'line_confirmation_sent_at' => now(),
                ]);
                
                Log::info('reservation_confirmation_sent', [
                    'reservation_id' => $reservation->id,
                    'customer_id' => $customer->id,
                    'method' => 'line',
                    'line_user_id' => $customer->line_user_id,
                ]);
            } else {
                Log::warning('reservation_confirmation_failed', [
                    'reservation_id' => $reservation->id,
                    'customer_id' => $customer->id,
                    'method' => 'line',
                    'line_user_id' => $customer->line_user_id,
                ]);
            }
            
            return $success;
            
        } catch (\Exception $e) {
            Log::error('reservation_confirmation_failed', [
                'reservation_id' => $reservation->id,
                'method' => 'line',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    /**
     * SMS確認通知を送信
     *
     * @param Reservation $reservation
     * @return bool
     */
    public function sendSmsConfirmation(Reservation $reservation): bool
    {
        try {
            $customer = $reservation->customer;
            $phone = $customer->phone;
            
            if (!$phone) {
                Log::info('reservation_confirmation_skip', [
                    'reservation_id' => $reservation->id,
                    'reason' => 'no_phone_number'
                ]);
                return false;
            }
            
            $message = $this->buildSmsMessage($reservation);
            $success = $this->smsService->sendSms($phone, $message);
            
            if ($success) {
                Log::info('reservation_confirmation_sent', [
                    'reservation_id' => $reservation->id,
                    'customer_id' => $customer->id,
                    'method' => 'sms',
                    'phone' => $phone,
                ]);
            } else {
                Log::warning('reservation_confirmation_failed', [
                    'reservation_id' => $reservation->id,
                    'customer_id' => $customer->id,
                    'method' => 'sms',
                    'phone' => $phone,
                ]);
            }
            
            return $success;
            
        } catch (\Exception $e) {
            Log::error('reservation_confirmation_failed', [
                'reservation_id' => $reservation->id,
                'method' => 'sms',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    /**
     * 確認通知送信完了をマーク
     *
     * @param Reservation $reservation
     * @param string $method
     * @return void
     */
    public function markConfirmationSent(Reservation $reservation, string $method): void
    {
        $reservation->update([
            'confirmation_sent_at' => now(),
            'confirmation_method' => $method,
        ]);
        
        Log::info('reservation_confirmation_marked', [
            'reservation_id' => $reservation->id,
            'method' => $method,
            'sent_at' => now()->toISOString()
        ]);
    }
    
    /**
     * 静穏時間かどうかをチェック
     *
     * @return bool
     */
    public function isQuietHours(): bool
    {
        $now = now();
        $start = Carbon::createFromTimeString(config('reservation.quiet_hours_start', '21:00'));
        $end = Carbon::createFromTimeString(config('reservation.quiet_hours_end', '08:00'));
        
        if ($start->gt($end)) {
            // 日をまたぐ場合（21:00-8:00）
            return $now->gte($start) || $now->lt($end);
        }
        
        return $now->between($start, $end);
    }
    
    /**
     * 翌営業時間までの遅延時間を計算
     *
     * @return int 秒数
     */
    public function getDelayUntilNextBusinessHours(): int
    {
        $now = now();
        $endTime = Carbon::createFromTimeString(config('reservation.quiet_hours_end', '08:00'));
        
        // 既に営業時間内の場合は即時実行
        if (!$this->isQuietHours()) {
            return 0;
        }
        
        // 翌日の営業開始時間まで
        if ($now->gt($endTime)) {
            $endTime = $endTime->addDay();
        }
        
        return $endTime->diffInSeconds($now);
    }
    
    /**
     * SMS用メッセージを構築
     *
     * @param Reservation $reservation
     * @return string
     */
    protected function buildSmsMessage(Reservation $reservation): string
    {
        $template = config('reservation.messages.sms_confirmation', 
            '【{store_name}】ご予約確認\n{reservation_date} {start_time} {menu_name}\n予約番号: {reservation_number}\nお待ちしております。'
        );
        
        return str_replace([
            '{store_name}',
            '{reservation_date}',
            '{start_time}',
            '{menu_name}',
            '{reservation_number}',
        ], [
            $reservation->store->name,
            $reservation->reservation_date->format('m/d(D)'),
            Carbon::parse($reservation->start_time)->format('H:i'),
            $reservation->menu->name ?? 'メニュー',
            $reservation->reservation_number,
        ], $template);
    }
}