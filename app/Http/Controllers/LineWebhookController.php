<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Store;
use App\Models\Customer;
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
        
        // 署名検証
        if (!$this->verifySignature($request, $store->line_channel_secret)) {
            Log::warning('LINE Webhook: 署名検証失敗', ['store_id' => $store->id]);
            return response()->json(['status' => 'error'], 403);
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
        
        // ウェルカムメッセージ送信などの処理を追加可能
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
}