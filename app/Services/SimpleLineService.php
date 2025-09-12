<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Reservation;
use App\Models\Customer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SimpleLineService
{
    private $store;
    
    public function __construct(?Store $store = null)
    {
        $this->store = $store;
    }
    
    /**
     * 予約確認送信
     */
    public function sendConfirmation(Reservation $reservation): bool
    {
        $store = $reservation->store;
        
        if (!$store->line_enabled || !$store->line_send_reservation_confirmation) {
            return false;
        }
        
        $variables = $this->getReservationVariables($reservation);
        $message = $this->applyVariables($store->line_reservation_message ?: $this->getDefaultConfirmationMessage(), $variables);
        
        return $this->sendToStore($store, $reservation->customer->line_user_id, $message);
    }
    
    /**
     * リマインダー送信
     */
    public function sendReminder(Reservation $reservation): bool
    {
        $store = $reservation->store;
        
        if (!$store->line_enabled || !$store->line_send_reminder) {
            return false;
        }
        
        $variables = $this->getReservationVariables($reservation);
        $message = $this->applyVariables($store->line_reminder_message ?: $this->getDefaultReminderMessage(), $variables);
        
        return $this->sendToStore($store, $reservation->customer->line_user_id, $message);
    }
    
    /**
     * 7日後フォローアップ送信
     */
    public function sendFollowup7Days(Customer $customer, Store $store): bool
    {
        if (!$store->line_enabled || !$store->line_send_followup) {
            return false;
        }
        
        $variables = [
            'customer_name' => $customer->last_name . ' ' . $customer->first_name . '様',
            'store_name' => $store->name,
        ];
        
        $message = $this->applyVariables($store->line_followup_message_7days ?: $this->getDefaultFollowup7Message(), $variables);
        
        return $this->sendToStore($store, $customer->line_user_id, $message);
    }
    
    /**
     * 15日後フォローアップ送信
     */
    public function sendFollowup15Days(Customer $customer, Store $store): bool
    {
        if (!$store->line_enabled || !$store->line_send_followup) {
            return false;
        }
        
        $variables = [
            'customer_name' => $customer->last_name . ' ' . $customer->first_name . '様',
            'store_name' => $store->name,
        ];
        
        $message = $this->applyVariables($store->line_followup_message_15days ?: $this->getDefaultFollowup15Message(), $variables);
        
        return $this->sendToStore($store, $customer->line_user_id, $message);
    }
    
    /**
     * プロモーション一斉送信（店舗別）
     */
    public function sendPromotion(Store $store, string $message, ?array $customerIds = null): array
    {
        if (!$store->line_enabled || !$store->line_send_promotion) {
            return ['success' => 0, 'failed' => 0, 'total' => 0];
        }
        
        $query = Customer::whereNotNull('line_user_id');
        
        // 特定顧客のみの場合
        if ($customerIds) {
            $query->whereIn('id', $customerIds);
        }
        
        // 店舗に関連する顧客のみ（予約履歴から取得）
        $query->whereHas('reservations', function($q) use ($store) {
            $q->where('store_id', $store->id);
        });
        
        $customers = $query->get();
        $success = 0;
        $failed = 0;
        
        foreach ($customers as $customer) {
            $variables = [
                'customer_name' => $customer->last_name . ' ' . $customer->first_name . '様',
                'store_name' => $store->name,
            ];
            $personalizedMessage = $this->applyVariables($message, $variables);
            
            if ($this->sendToStore($store, $customer->line_user_id, $personalizedMessage)) {
                $success++;
            } else {
                $failed++;
            }
            
            usleep(100000); // 0.1秒待機（レート制限対策）
        }
        
        return ['success' => $success, 'failed' => $failed, 'total' => $customers->count()];
    }
    
    /**
     * 店舗のLINE設定を使用して送信
     */
    private function sendToStore(Store $store, ?string $lineUserId, string $message): bool
    {
        if (!$lineUserId || !$store->line_channel_access_token) {
            return false;
        }
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $store->line_channel_access_token,
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
            
            // ログ記録
            if ($response->successful()) {
                Log::info('LINE送信成功', [
                    'store_id' => $store->id,
                    'line_user_id' => $lineUserId,
                ]);
            } else {
                Log::error('LINE送信失敗', [
                    'store_id' => $store->id,
                    'line_user_id' => $lineUserId,
                    'response' => $response->body(),
                ]);
            }
            
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('LINE送信エラー', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * 汎用メッセージ送信
     */
    public function sendMessage(Store $store, string $lineUserId, string $message): bool
    {
        return $this->sendToStore($store, $lineUserId, $message);
    }
    
    /**
     * 変数を適用
     */
    private function applyVariables(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        return $template;
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
    
    /**
     * デフォルトメッセージテンプレート
     */
    private function getDefaultConfirmationMessage(): string
    {
        return "{{customer_name}}\n\nご予約ありがとうございます。\n日時: {{reservation_date}} {{reservation_time}}\nメニュー: {{menu_name}}\n\nお待ちしております。\n{{store_name}}";
    }
    
    private function getDefaultReminderMessage(): string
    {
        return "{{customer_name}}\n\n明日のご予約のお知らせです。\n日時: {{reservation_date}} {{reservation_time}}\n\nお気をつけてお越しください。\n{{store_name}}";
    }
    
    private function getDefaultFollowup7Message(): string
    {
        return "{{customer_name}}\n\n前回のご来店から1週間が経ちました。\n目の調子はいかがでしょうか？\n\n次回のご予約はこちらから\n{{store_name}}";
    }
    
    private function getDefaultFollowup15Message(): string
    {
        return "{{customer_name}}\n\n前回のご来店から2週間が経ちました。\n目の調子はいかがでしょうか？\n\n定期的なケアで効果を持続させませんか？\n{{store_name}}";
    }
}