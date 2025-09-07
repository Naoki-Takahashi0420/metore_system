<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;

class CustomerSubscriptionController extends Controller
{
    /**
     * 顧客のサブスクリプション情報を取得
     */
    public function show(Request $request)
    {
        $token = str_replace('Bearer ', '', $request->header('Authorization'));
        
        if (!$token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        // customer_tokenから顧客IDを取得（簡易認証）
        // 本来はより安全な認証を実装すべき
        $customerData = json_decode(base64_decode($token), true);
        $customerId = $customerData['id'] ?? null;
        
        if (!$customerId) {
            // トークンから取得できない場合、電話番号で検索（テスト用）
            $phone = $customerData['phone'] ?? null;
            if ($phone) {
                $customer = Customer::where('phone', $phone)->first();
            } else {
                return response()->json(['error' => 'Invalid token'], 401);
            }
        } else {
            $customer = Customer::find($customerId);
        }
        
        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }
        
        // アクティブなサブスクリプションを取得
        $subscription = $customer->activeSubscription()->with('store')->first();
        
        if (!$subscription) {
            return response()->json(['subscription' => null]);
        }
        
        // プラン情報を取得
        $plan = null;
        if ($subscription->plan_id) {
            $plan = \App\Models\SubscriptionPlan::find($subscription->plan_id);
        }
        
        return response()->json([
            'subscription' => [
                'id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'plan_name' => $plan ? $plan->name : $subscription->plan_name,
                'plan_type' => $subscription->plan_type,
                'monthly_limit' => $subscription->monthly_limit,
                'monthly_price' => $subscription->monthly_price,
                'current_month_visits' => $subscription->current_month_visits,
                'billing_start_date' => $subscription->billing_start_date?->format('Y-m-d'),
                'service_start_date' => $subscription->service_start_date?->format('Y-m-d'),
                'end_date' => $subscription->end_date?->format('Y-m-d'),
                'status' => $subscription->status,
                'store' => $subscription->store ? [
                    'id' => $subscription->store->id,
                    'name' => $subscription->store->name,
                ] : null,
                'remaining_visits' => $subscription->monthly_limit 
                    ? max(0, $subscription->monthly_limit - $subscription->current_month_visits)
                    : null,
            ]
        ]);
    }
}