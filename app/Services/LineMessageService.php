<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Customer;
use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LineMessageService
{
    private $channelToken;
    private $apiUrl = 'https://api.line.me/v2/bot/message/push';
    
    public function __construct()
    {
        $this->channelToken = env('LINE_CHANNEL_ACCESS_TOKEN');
    }
    
    /**
     * 店舗ごとのトークンを設定
     */
    public function setChannelToken(string $token): void
    {
        $this->channelToken = $token;
    }
    
    /**
     * ① 予約確認メッセージを送る
     */
    public function sendReservationConfirmation(Reservation $reservation)
    {
        $customer = $reservation->customer;
        $lineUserId = $customer->line_user_id ?? null;

        if (!$lineUserId) {
            return false;
        }

        // 店舗のLINE設定を取得してトークンを設定
        $store = $reservation->store;
        if ($store && $store->line_enabled && $store->line_channel_access_token) {
            $this->setChannelToken($store->line_channel_access_token);
        } else {
            Log::warning('Store LINE settings not configured for confirmation', [
                'reservation_id' => $reservation->id,
                'store_id' => $store?->id
            ]);
            return false;
        }

        $message = $this->buildConfirmationMessage($reservation);

        return $this->sendMessage($lineUserId, $message);
    }
    
    /**
     * ② リマインダーを送る（時間指定）
     */
    public function sendReminder(Reservation $reservation, string $timing)
    {
        $customer = $reservation->customer;
        $lineUserId = $customer->line_user_id ?? null;

        if (!$lineUserId) {
            return false;
        }

        // 店舗のLINE設定を取得してトークンを設定
        $store = $reservation->store;
        if ($store && $store->line_enabled && $store->line_channel_access_token) {
            $this->setChannelToken($store->line_channel_access_token);
        } else {
            Log::warning('Store LINE settings not configured for reminder', [
                'reservation_id' => $reservation->id,
                'store_id' => $store?->id
            ]);
            return false;
        }

        $message = $this->buildReminderMessage($reservation, $timing);

        return $this->sendMessage($lineUserId, $message);
    }
    
    /**
     * ③ プロモーション一斉送信
     */
    public function sendPromotion(string $message, array $customerIds = [])
    {
        // 対象顧客を取得（店舗情報も含む）
        $query = Customer::with('store')->whereNotNull('line_user_id');

        if (!empty($customerIds)) {
            $query->whereIn('id', $customerIds);
        }

        $customers = $query->get();
        $successCount = 0;
        $failCount = 0;

        foreach ($customers as $customer) {
            // 顧客の店舗のLINE設定を取得してトークンを設定
            $store = $customer->store;
            if ($store && $store->line_enabled && $store->line_channel_access_token) {
                $this->setChannelToken($store->line_channel_access_token);
            } else {
                Log::warning('Store LINE settings not configured for promotion', [
                    'customer_id' => $customer->id,
                    'store_id' => $store?->id
                ]);
                $failCount++;
                continue;
            }

            if ($this->sendMessage($customer->line_user_id, $message)) {
                $successCount++;
            } else {
                $failCount++;
            }
            // レート制限対策で少し待機
            usleep(100000); // 0.1秒
        }

        return [
            'total' => $customers->count(),
            'success' => $successCount,
            'failed' => $failCount
        ];
    }
    
    /**
     * ④ 初回客フォロー（30日/60日後）
     */
    public function sendFirstTimeFollowUp(Customer $customer, int $days)
    {
        $lineUserId = $customer->line_user_id ?? null;

        if (!$lineUserId) {
            return false;
        }

        // 顧客の店舗のLINE設定を取得してトークンを設定
        $store = $customer->store;
        if ($store && $store->line_enabled && $store->line_channel_access_token) {
            $this->setChannelToken($store->line_channel_access_token);
        } else {
            Log::warning('Store LINE settings not configured for follow-up', [
                'customer_id' => $customer->id,
                'store_id' => $store?->id
            ]);
            return false;
        }

        $message = $this->buildFollowUpMessage($customer, $days);

        return $this->sendMessage($lineUserId, $message);
    }
    
    /**
     * LINE APIでメッセージ送信
     */
    public function sendMessage(string $lineUserId, $message)
    {
        // ローカル環境では送信しない
        if (config('app.env') === 'local') {
            Log::info('[LOCAL] LINE送信をスキップ（ローカル環境）', [
                'line_user_id' => $lineUserId,
                'message_length' => is_string($message) ? strlen($message) : 'array',
            ]);
            return true;
        }

        if (!$this->channelToken) {
            Log::warning('LINE Channel Token is not set');
            return false;
        }

        try {
            // メッセージの形式を判定
            $messages = [];
            if (is_string($message)) {
                // テキストメッセージ
                $messages[] = [
                    'type' => 'text',
                    'text' => $message
                ];
            } elseif (is_array($message)) {
                // Flexメッセージまたは構造化メッセージ
                $messages[] = $message;
            } else {
                Log::error('Invalid message format', ['message' => $message]);
                return false;
            }
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->channelToken,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'to' => $lineUserId,
                'messages' => $messages
            ]);
            
            if ($response->successful()) {
                Log::info('LINE message sent successfully', ['user_id' => $lineUserId]);
                return true;
            } else {
                Log::error('LINE API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('LINE message send failed', [
                'error' => $e->getMessage(),
                'user_id' => $lineUserId
            ]);
            return false;
        }
    }
    
    /**
     * 予約確認メッセージ作成
     */
    private function buildConfirmationMessage(Reservation $reservation): string
    {
        $date = Carbon::parse($reservation->reservation_date)->format('Y年m月d日');
        $time = $reservation->start_time ? Carbon::parse($reservation->start_time)->format('H:i') : '';

        return "🎉 ご予約ありがとうございます！\n\n" .
               "📅 日時: {$date} {$time}\n" .
               "📍 店舗: {$reservation->store->name}\n" .
               "💡 メニュー: {$reservation->menu->name}\n\n" .
               "ご不明な点がございましたらお気軽にお問い合わせください。\n" .
               "当日お会いできることを楽しみにしております！";
    }
    
    /**
     * リマインダーメッセージ作成
     */
    private function buildReminderMessage(Reservation $reservation, string $timing): string
    {
        $date = Carbon::parse($reservation->reservation_date)->format('Y年m月d日');
        $time = $reservation->start_time ? Carbon::parse($reservation->start_time)->format('H:i') : '';
        
        $timingText = match($timing) {
            '24h' => '明日',
            '3h' => '本日',
            '1h' => 'まもなく',
            default => ''
        };
        
        return "⏰ 予約リマインダー\n\n" .
               "{$timingText}のご予約をお忘れなく！\n\n" .
               "📅 日時: {$date} {$time}\n" .
               "📍 店舗: {$reservation->store->name}\n" .
               "💡 メニュー: {$reservation->menu->name}\n\n" .
               "お気をつけてお越しください。";
    }
    
    /**
     * ⑤ LINE連携ボタンメッセージを送信
     */
    public function sendLinkingButton(string $lineUserId, string $linkingUrl, Store $store)
    {
        if (!$lineUserId) {
            return false;
        }

        // 店舗のLINE設定を取得してトークンを設定
        if ($store && $store->line_enabled && $store->line_channel_access_token) {
            $this->setChannelToken($store->line_channel_access_token);
        } else {
            Log::warning('Store LINE settings not configured for linking button', [
                'store_id' => $store?->id
            ]);
            return false;
        }

        $message = $this->buildLinkingMessage($linkingUrl, $store);

        return $this->sendMessage($lineUserId, $message);
    }
    
    /**
     * フォローアップメッセージ作成
     */
    private function buildFollowUpMessage(Customer $customer, int $days): string
    {
        $name = $customer->name ?? 'お客様';
        
        if ($days === 30) {
            return "{$name}様\n\n" .
                   "先日はご来店ありがとうございました！\n" .
                   "その後、目の調子はいかがですか？\n\n" .
                   "継続的なトレーニングが効果的です。\n" .
                   "ぜひ2回目のご予約もご検討ください。\n\n" .
                   "🎁 今なら2回目10%OFFクーポンをプレゼント！\n" .
                   "ご予約はこちら: [予約URL]";
        } else {
            return "{$name}様\n\n" .
                   "ご無沙汰しております。\n" .
                   "前回のご来店から2ヶ月が経ちました。\n\n" .
                   "視力トレーニングの効果を維持するために、\n" .
                   "定期的なメンテナンスをおすすめします。\n\n" .
                   "🎁 特別割引20%OFFでご案内いたします。\n" .
                   "ご予約はこちら: [予約URL]";
        }
    }
    
    /**
     * LINE連携メッセージ作成
     */
    private function buildLinkingMessage(string $linkingUrl, Store $store): string
    {
        return "🔗 アカウント連携のご案内\n\n" .
               "{$store->name}のLINEアカウントと連携することで、\n" .
               "以下のサービスをご利用いただけます：\n\n" .
               "✅ 予約の確認・変更・キャンセル\n" .
               "✅ 来店前日のリマインダー通知\n" .
               "✅ お得なキャンペーン情報\n\n" .
               "👇 こちらのリンクから連携してください\n" .
               $linkingUrl;
    }
}