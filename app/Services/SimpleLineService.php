<?php

namespace App\Services;

use App\Models\LineSetting;
use App\Models\Reservation;
use App\Models\Customer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SimpleLineService
{
    private $channelToken;
    private $settings;
    
    public function __construct()
    {
        $this->channelToken = env('LINE_CHANNEL_ACCESS_TOKEN');
        $this->settings = LineSetting::getSettings();
    }
    
    /**
     * 予約確認送信
     */
    public function sendConfirmation(Reservation $reservation): bool
    {
        if (!$this->settings->send_confirmation || !$this->settings->message_confirmation) {
            return false;
        }
        
        $variables = $this->getReservationVariables($reservation);
        $message = $this->settings->applyVariables($this->settings->message_confirmation, $variables);
        
        return $this->send($reservation->customer->line_user_id, $message);
    }
    
    /**
     * 24時間前リマインダー
     */
    public function sendReminder24h(Reservation $reservation): bool
    {
        if (!$this->settings->send_reminder_24h || !$this->settings->message_reminder_24h) {
            return false;
        }
        
        $variables = $this->getReservationVariables($reservation);
        $message = $this->settings->applyVariables($this->settings->message_reminder_24h, $variables);
        
        return $this->send($reservation->customer->line_user_id, $message);
    }
    
    /**
     * 3時間前リマインダー
     */
    public function sendReminder3h(Reservation $reservation): bool
    {
        if (!$this->settings->send_reminder_3h || !$this->settings->message_reminder_3h) {
            return false;
        }
        
        $variables = $this->getReservationVariables($reservation);
        $message = $this->settings->applyVariables($this->settings->message_reminder_3h, $variables);
        
        return $this->send($reservation->customer->line_user_id, $message);
    }
    
    /**
     * 30日後フォロー
     */
    public function sendFollow30d(Customer $customer): bool
    {
        if (!$this->settings->send_follow_30d || !$this->settings->message_follow_30d) {
            return false;
        }
        
        $variables = [
            'customer_name' => $customer->name ?? 'お客様',
        ];
        $message = $this->settings->applyVariables($this->settings->message_follow_30d, $variables);
        
        return $this->send($customer->line_user_id, $message);
    }
    
    /**
     * 60日後フォロー
     */
    public function sendFollow60d(Customer $customer): bool
    {
        if (!$this->settings->send_follow_60d || !$this->settings->message_follow_60d) {
            return false;
        }
        
        $variables = [
            'customer_name' => $customer->name ?? 'お客様',
        ];
        $message = $this->settings->applyVariables($this->settings->message_follow_60d, $variables);
        
        return $this->send($customer->line_user_id, $message);
    }
    
    /**
     * プロモーション一斉送信
     */
    public function sendPromotion(string $message): array
    {
        $customers = Customer::whereNotNull('line_user_id')->get();
        $success = 0;
        $failed = 0;
        
        foreach ($customers as $customer) {
            $variables = [
                'customer_name' => $customer->name ?? 'お客様',
            ];
            $personalizedMessage = str_replace('{{customer_name}}', $variables['customer_name'], $message);
            
            if ($this->send($customer->line_user_id, $personalizedMessage)) {
                $success++;
            } else {
                $failed++;
            }
            
            usleep(100000); // 0.1秒待機（レート制限対策）
        }
        
        return ['success' => $success, 'failed' => $failed, 'total' => $customers->count()];
    }
    
    /**
     * LINE API送信
     */
    private function send(?string $lineUserId, string $message): bool
    {
        if (!$lineUserId || !$this->channelToken) {
            return false;
        }
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->channelToken,
                'Content-Type' => 'application/json',
            ])->post('https://api.line.me/v2/bot/message/push', [
                'to' => $lineUserId,
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => $message
                    ]
                ]
            ]);
            
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('LINE送信エラー: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 予約情報から変数を取得
     */
    private function getReservationVariables(Reservation $reservation): array
    {
        return [
            'customer_name' => $reservation->customer->name ?? 'お客様',
            'reservation_date' => Carbon::parse($reservation->reservation_date)->format('Y年m月d日'),
            'reservation_time' => Carbon::parse($reservation->reservation_date)->format('H:i'),
            'store_name' => $reservation->store->name ?? '',
            'menu_name' => $reservation->menu->name ?? '',
            'menu_price' => number_format($reservation->menu->price ?? 0) . '円',
        ];
    }
}