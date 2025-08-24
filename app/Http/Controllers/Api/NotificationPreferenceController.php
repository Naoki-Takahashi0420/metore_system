<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationPreferenceController extends Controller
{
    /**
     * 通知設定の取得
     */
    public function show(Request $request): JsonResponse
    {
        $customer = $request->user();
        
        if (!$customer instanceof Customer) {
            return response()->json([
                'success' => false,
                'message' => '顧客情報が見つかりません',
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'sms_notifications_enabled' => $customer->sms_notifications_enabled,
                'notification_preferences' => $customer->notification_preferences ?? [],
            ],
        ]);
    }
    
    /**
     * 通知設定の更新
     */
    public function update(Request $request): JsonResponse
    {
        $customer = $request->user();
        
        if (!$customer instanceof Customer) {
            return response()->json([
                'success' => false,
                'message' => '顧客情報が見つかりません',
            ], 404);
        }
        
        $validated = $request->validate([
            'sms_notifications_enabled' => 'sometimes|boolean',
            'notification_preferences' => 'sometimes|array',
            'notification_preferences.reminder_days' => 'sometimes|integer|min:1|max:7',
            'notification_preferences.reminder_time' => 'sometimes|string|in:morning,afternoon,evening',
        ]);
        
        if (isset($validated['sms_notifications_enabled'])) {
            $customer->sms_notifications_enabled = $validated['sms_notifications_enabled'];
        }
        
        if (isset($validated['notification_preferences'])) {
            $preferences = $customer->notification_preferences ?? [];
            $customer->notification_preferences = array_merge($preferences, $validated['notification_preferences']);
        }
        
        $customer->save();
        
        return response()->json([
            'success' => true,
            'message' => '通知設定を更新しました',
            'data' => [
                'sms_notifications_enabled' => $customer->sms_notifications_enabled,
                'notification_preferences' => $customer->notification_preferences,
            ],
        ]);
    }
    
    /**
     * SMS通知を無効化（配信停止リンクから）
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);
        
        // トークンから顧客を特定（簡易版：実際はより安全な実装が必要）
        $customerId = decrypt($validated['token']);
        $customer = Customer::find($customerId);
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => '無効なリンクです',
            ], 404);
        }
        
        $customer->sms_notifications_enabled = false;
        $customer->save();
        
        return response()->json([
            'success' => true,
            'message' => 'SMS通知を停止しました。設定はいつでも変更できます。',
        ]);
    }
}