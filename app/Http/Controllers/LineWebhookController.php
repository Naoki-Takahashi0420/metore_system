<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Store;
use App\Models\Customer;
use App\Models\CustomerAccessToken;
use App\Services\SimpleLineService;
use Illuminate\Support\Facades\Log;

class LineWebhookController extends Controller
{
    /**
     * 店舗別のLINE Webhook処理
     * URLパターン: /api/line/webhook/{store_code}
     */
    public function handle(Request $request, $storeCode)
    {
        // 店舗を特定
        $store = Store::where('code', $storeCode)->first();
        if (!$store || !$store->line_enabled) {
            Log::warning('LINE Webhook: 無効な店舗コード', ['code' => $storeCode]);
            return response()->json(['status' => 'error'], 400);
        }
        
        // 署名検証（LINE検証リクエストの場合はスキップ）
        $events = $request->input('events', []);
        Log::info('LINE Webhook受信', [
            'store_code' => $storeCode,
            'store_id' => $store->id,
            'events_count' => count($events),
            'signature' => $request->header('X-Line-Signature'),
            'has_secret' => !empty($store->line_channel_secret),
            'secret_length' => strlen($store->line_channel_secret),
        ]);
        
        if (!empty($events) && !$this->verifySignature($request, $store->line_channel_secret)) {
            Log::error('LINE Webhook: 署名検証失敗', [
                'store_id' => $store->id,
                'signature' => $request->header('X-Line-Signature'),
                'body_length' => strlen($request->getContent()),
            ]);
            return response()->json(['status' => 'error'], 401);
        }
        
        $events = $request->input('events', []);
        
        foreach ($events as $event) {
            $this->processEvent($event, $store);
        }
        
        return response()->json(['status' => 'ok']);
    }
    
    /**
     * 署名検証
     */
    private function verifySignature(Request $request, $channelSecret)
    {
        $signature = $request->header('X-Line-Signature');
        if (!$signature) {
            return false;
        }
        
        $body = $request->getContent();
        $hash = hash_hmac('sha256', $body, $channelSecret, true);
        $expectedSignature = base64_encode($hash);
        
        return $signature === $expectedSignature;
    }
    
    /**
     * イベント処理
     */
    private function processEvent($event, Store $store)
    {
        $type = $event['type'] ?? '';
        
        switch ($type) {
            case 'follow':
                // 友だち追加イベント
                $this->handleFollow($event, $store);
                break;
                
            case 'unfollow':
                // ブロックイベント
                $this->handleUnfollow($event, $store);
                break;
                
            case 'message':
                // メッセージ受信イベント
                $this->handleMessage($event, $store);
                break;
                
            default:
                Log::info('LINE Webhook: 未処理のイベントタイプ', [
                    'type' => $type,
                    'store_id' => $store->id
                ]);
        }
    }
    
    /**
     * 友だち追加処理
     */
    private function handleFollow($event, Store $store)
    {
        $lineUserId = $event['source']['userId'] ?? null;
        if (!$lineUserId) {
            return;
        }

        Log::info('LINE 友だち追加', [
            'line_user_id' => $lineUserId,
            'store_id' => $store->id
        ]);

        // 友だち追加時は何もしない
        // LINE連携は予約完了時のLIFFボタン経由で行う
        // あいさつメッセージはLINE公式アカウント管理画面で設定
    }
    
    /**
     * ブロック処理
     */
    private function handleUnfollow($event, Store $store)
    {
        $lineUserId = $event['source']['userId'] ?? null;
        if (!$lineUserId) {
            return;
        }
        
        // 該当する顧客のLINE連携を解除
        Customer::where('line_user_id', $lineUserId)
            ->update(['line_user_id' => null]);
        
        Log::info('LINE ブロック', [
            'line_user_id' => $lineUserId,
            'store_id' => $store->id
        ]);
    }
    
    /**
     * メッセージ受信処理
     */
    private function handleMessage($event, Store $store)
    {
        $lineUserId = $event['source']['userId'] ?? null;
        $messageType = $event['message']['type'] ?? '';
        $text = $event['message']['text'] ?? '';
        
        if (!$lineUserId || $messageType !== 'text') {
            return;
        }
        
        Log::info('LINE メッセージ受信', [
            'line_user_id' => $lineUserId,
            'store_id' => $store->id,
            'type' => $messageType,
            'text' => $text
        ]);
        
        // 6桁の連携コードを検出
        $codePattern = '/^[0-9]{6}$/';
        $cleanText = preg_replace('/[^0-9]/', '', $text);
        
        if (preg_match($codePattern, $cleanText)) {
            // 連携コードで顧客を検索
            $this->linkCustomerByCode($lineUserId, $cleanText, $store);
            return;
        }
        
        // 電話番号らしき文字列を検出（フォールバック）
        $phonePattern = '/^0[0-9]{9,10}$/';
        if (preg_match($phonePattern, $cleanText)) {
            $this->linkCustomerByPhone($lineUserId, $cleanText, $store);
            return;
        }
        
        // 自動応答などの処理を追加可能
    }

    /**
     * トークンによる顧客連携
     */
    private function linkCustomerByToken(string $lineUserId, string $token, Store $store): void
    {
        try {
            // トークンの有効性チェック
            $accessToken = CustomerAccessToken::where('token', $token)
                ->where('store_id', $store->id)
                ->where('purpose', 'line_linking')
                ->first();

            if (!$accessToken || !$accessToken->isValid()) {
                Log::warning('LINE連携: 無効なトークン', [
                    'token' => $token,
                    'line_user_id' => $lineUserId,
                    'store_id' => $store->id
                ]);
                return;
            }

            // 顧客を取得
            $customer = $accessToken->customer;
            if (!$customer) {
                Log::error('LINE連携: 顧客が見つからない', [
                    'token' => $token,
                    'customer_id' => $accessToken->customer_id
                ]);
                return;
            }

            // 他の顧客が既に同じLINEユーザーIDを使用していないかチェック
            $existingCustomer = Customer::where('line_user_id', $lineUserId)
                ->where('id', '!=', $customer->id)
                ->first();

            if ($existingCustomer) {
                Log::warning('LINE連携: 既に他の顧客が同じLINEユーザーIDを使用', [
                    'existing_customer_id' => $existingCustomer->id,
                    'new_customer_id' => $customer->id,
                    'line_user_id' => $lineUserId
                ]);
                return;
            }

            // 顧客にLINEユーザーIDを関連付け
            $customer->linkToLine($lineUserId);

            // トークン使用を記録
            $accessToken->recordUsage();

            Log::info('LINE連携成功', [
                'customer_id' => $customer->id,
                'line_user_id' => $lineUserId,
                'store_id' => $store->id
            ]);

            // 連携完了メッセージを送信
            $this->sendLinkingCompleteMessage($lineUserId, $customer, $store);

        } catch (\Exception $e) {
            Log::error('LINE連携エラー', [
                'token' => $token,
                'line_user_id' => $lineUserId,
                'store_id' => $store->id,
                'error' => $e->getMessage()
            ]);
        }
    }


    /**
     * 連携完了メッセージ送信
     */
    private function sendLinkingCompleteMessage(string $lineUserId, Customer $customer, Store $store): void
    {
        $lineService = new SimpleLineService();
        
        // 予約情報があれば含める
        $accessToken = CustomerAccessToken::where('customer_id', $customer->id)
            ->where('store_id', $store->id)
            ->where('purpose', 'line_linking')
            ->latest()
            ->first();

        $reservationInfo = '';
        if ($accessToken && isset($accessToken->metadata['reservation_number'])) {
            $reservationInfo = "\n\n【ご予約情報】\n予約番号: {$accessToken->metadata['reservation_number']}";
        }

        $message = "LINE連携が完了しました！\n{$customer->last_name} {$customer->first_name}様\n\n今後、予約の変更・キャンセル、リマインダー通知などをLINEでお受け取りいただけます。{$reservationInfo}";
        
        $lineService->sendMessage($store, $lineUserId, $message);
    }
    
    /**
     * 電話番号による顧客連携
     */
    private function linkCustomerByPhone(string $lineUserId, string $phone, Store $store): void
    {
        try {
            // 電話番号で顧客を検索
            $customer = Customer::where('phone', $phone)->first();
            
            if (!$customer) {
                $lineService = new SimpleLineService();
                $message = "申し訳ございません。この電話番号で予約が見つかりませんでした。\n" .
                           "予約時の電話番号を再度ご確認ください。";
                $lineService->sendMessage($store, $lineUserId, $message);
                return;
            }
            
            // 他の顧客が既に同じLINEユーザーIDを使用していないかチェック
            $existingCustomer = Customer::where('line_user_id', $lineUserId)
                ->where('id', '!=', $customer->id)
                ->first();
            
            if ($existingCustomer) {
                Log::warning('LINE連携: 既に他の顧客が同じLINEユーザーIDを使用', [
                    'existing_customer_id' => $existingCustomer->id,
                    'new_customer_id' => $customer->id,
                    'line_user_id' => $lineUserId
                ]);
                
                $lineService = new SimpleLineService();
                $message = "このLINEアカウントは既に別の電話番号と連携されています。";
                $lineService->sendMessage($store, $lineUserId, $message);
                return;
            }
            
            // 顧客にLINEユーザーIDを関連付け
            $customer->line_user_id = $lineUserId;
            $customer->line_notifications_enabled = true;
            $customer->save();
            
            Log::info('LINE連携成功（電話番号）', [
                'customer_id' => $customer->id,
                'line_user_id' => $lineUserId,
                'store_id' => $store->id
            ]);
            
            // 連携完了メッセージを送信
            $lineService = new SimpleLineService();
            $message = "✅ LINE連携が完了しました！\n\n" .
                       "{$customer->last_name} {$customer->first_name}様\n\n" .
                       "今後、予約のリマインダー通知やお得な情報をLINEでお受け取りいただけます。\n\n" .
                       "【設定完了】\n" .
                       "・予約前日のリマインダー\n" .
                       "・予約変更・キャンセルのお知らせ\n" .
                       "・キャンペーン情報";
            $lineService->sendMessage($store, $lineUserId, $message);
            
        } catch (\Exception $e) {
            Log::error('LINE連携エラー（電話番号）', [
                'phone' => $phone,
                'line_user_id' => $lineUserId,
                'store_id' => $store->id,
                'error' => $e->getMessage()
            ]);
            
            $lineService = new SimpleLineService();
            $message = "連携処理中にエラーが発生しました。しばらく待ってから再度お試しください。";
            $lineService->sendMessage($store, $lineUserId, $message);
        }
    }
    
    /**
     * 連携コードによる顧客連携
     */
    private function linkCustomerByCode(string $lineUserId, string $code, Store $store): void
    {
        try {
            // 連携コードでトークンを検索
            $token = CustomerAccessToken::where('store_id', $store->id)
                ->where('purpose', 'line_linking')
                ->whereJsonContains('metadata->linking_code', $code)
                ->where('created_at', '>=', now()->subHours(24)) // 24時間以内
                ->first();
            
            if (!$token || !$token->customer) {
                $lineService = new SimpleLineService();
                $message = "連携コードが見つかりません。予約完了時に表示された6桁のコードをご確認ください。";
                $lineService->sendMessage($store, $lineUserId, $message);
                return;
            }
            
            $customer = $token->customer;
            
            // 既に他の顧客が同じLINEユーザーIDを使用していないかチェック
            $existingCustomer = Customer::where('line_user_id', $lineUserId)
                ->where('id', '!=', $customer->id)
                ->first();
            
            if ($existingCustomer) {
                $lineService = new SimpleLineService();
                $message = "このLINEアカウントは既に別のお客様と連携されています。";
                $lineService->sendMessage($store, $lineUserId, $message);
                return;
            }
            
            // 顧客にLINEユーザーIDを関連付け
            $customer->line_user_id = $lineUserId;
            $customer->line_notifications_enabled = true;
            $customer->save();
            
            // トークンを使用済みにする
            $token->recordUsage();
            
            Log::info('LINE連携成功（連携コード）', [
                'customer_id' => $customer->id,
                'line_user_id' => $lineUserId,
                'store_id' => $store->id
            ]);
            
            // 連携完了メッセージを送信
            $lineService = new SimpleLineService();
            $reservationInfo = '';
            if (isset($token->metadata['reservation_number'])) {
                $reservationInfo = "\n\n予約番号: {$token->metadata['reservation_number']}";
            }
            
            $message = "✅ LINE連携が完了しました！\n\n" .
                       "{$customer->last_name} {$customer->first_name}様\n" .
                       "今後、予約のリマインダー通知をLINEでお受け取りいただけます。{$reservationInfo}";
            $lineService->sendMessage($store, $lineUserId, $message);
            
        } catch (\Exception $e) {
            Log::error('LINE連携エラー（連携コード）', [
                'code' => $code,
                'line_user_id' => $lineUserId,
                'store_id' => $store->id,
                'error' => $e->getMessage()
            ]);
            
            $lineService = new SimpleLineService();
            $message = "連携処理中にエラーが発生しました。しばらく待ってから再度お試しください。";
            $lineService->sendMessage($store, $lineUserId, $message);
        }
    }
    
}