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
        
        // URLのトークンパラメータをチェック
        $token = request()->input('token');
        
        if ($token) {
            // トークンベース顧客連携処理
            $this->linkCustomerByToken($lineUserId, $token, $store);
        } else {
            // 通常の友だち追加の場合、ウェルカムメッセージ送信
            $this->sendWelcomeMessage($lineUserId, $store);
        }
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
        
        if (!$lineUserId) {
            return;
        }
        
        Log::info('LINE メッセージ受信', [
            'line_user_id' => $lineUserId,
            'store_id' => $store->id,
            'type' => $messageType,
            'text' => $text
        ]);
        
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
     * ウェルカムメッセージ送信
     */
    private function sendWelcomeMessage(string $lineUserId, Store $store): void
    {
        $lineService = new SimpleLineService();
        
        $message = "いらっしゃいませ！\n{$store->name}のLINE公式アカウントにご登録いただき、ありがとうございます。\n\nこちらから予約の確認や変更、キャンセルが可能です。";
        
        $lineService->sendMessage($store, $lineUserId, $message);
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
}