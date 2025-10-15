<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\CustomerSubscription;

class CustomerSubscriptionController extends Controller
{
    /**
     * すべてのサブスクリプション情報を取得
     */
    public function index(Request $request)
    {
        $customer = $request->user();

        if (!$customer) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // 同じ電話番号を持つ全顧客IDを取得
        $customerIds = Customer::where('phone', $customer->phone)
            ->pluck('id')
            ->toArray();

        // サブスクリプションを取得（店舗IDがある場合のみフィルタリング）
        $query = CustomerSubscription::whereIn('customer_id', $customerIds)
            ->where('status', 'active');

        // 顧客に店舗IDが設定されている場合のみ店舗でフィルタリング
        if ($customer->store_id) {
            $query->where('store_id', $customer->store_id);
        }

        $subscriptions = $query->with(['store'])->get();
        
        return response()->json([
            'data' => $subscriptions->map(function ($sub) {
                // current_month_visitsは自動計算されるため、リセット処理不要

                $remaining = $sub->monthly_limit ?
                    max(0, $sub->monthly_limit - $sub->current_month_visits) :
                    ($sub->remaining_sessions ?? 0);
                    
                // プランがない場合のメニューID取得
                $menuId = null;
                if ($sub->plan_id) {
                    $plan = \App\Models\SubscriptionPlan::find($sub->plan_id);
                    $menuId = $plan ? $plan->menu_id : null;
                }
                    
                return [
                    'id' => $sub->id,
                    'status' => $sub->status,
                    'store_id' => $sub->store_id,
                    'menu_id' => $sub->menu_id ?? $menuId,
                    'plan_name' => $sub->plan_name ?? 'プラン',
                    'plan' => [
                        'id' => $sub->plan_id,
                        'name' => $sub->plan_name ?? 'プラン',
                        'menu_id' => $menuId,
                    ],
                    'remaining_sessions' => $remaining,
                    'monthly_limit' => $sub->monthly_limit,
                    'current_month_visits' => $sub->current_month_visits,
                    // 期間情報を追加
                    'period_start' => $sub->getCurrentPeriodStart()?->format('Y-m-d'),
                    'period_end' => $sub->getCurrentPeriodEnd()?->format('Y-m-d'),
                    'next_reset_date' => $sub->getNextResetDate()?->format('Y-m-d'),
                    'days_until_reset' => $sub->getDaysUntilReset(),
                    'store' => $sub->store ? [
                        'id' => $sub->store->id,
                        'name' => $sub->store->name,
                    ] : null,
                ];
            })
        ]);
    }
    
    /**
     * サブスク予約用のセッション設定
     */
    public function setupSession(Request $request)
    {
        try {
            $validated = $request->validate([
                'customer_id' => 'required|exists:customers,id',
                'subscription_id' => 'required|exists:customer_subscriptions,id',
                'store_id' => 'required|exists:stores,id',
                'menu_id' => 'required|exists:menus,id'
            ]);
            
            // メニュー情報を取得
            $menu = \App\Models\Menu::find($validated['menu_id']);
            if (!$menu) {
                return response()->json(['success' => false, 'message' => 'メニューが見つかりません'], 404);
            }
            
            // セッションに必要な情報を設定
            session([
                'selected_store_id' => $validated['store_id'],
                'reservation_menu' => $menu,
                'is_subscription_booking' => true,
                'subscription_id' => $validated['subscription_id'],
                'customer_id' => $validated['customer_id'],
                'from_mypage' => true
            ]);
            
            \Log::info('サブスク予約セッション設定完了', [
                'customer_id' => $validated['customer_id'],
                'store_id' => $validated['store_id'],
                'menu_id' => $validated['menu_id']
            ]);
            
            return response()->json(['success' => true]);
            
        } catch (\Exception $e) {
            \Log::error('サブスク予約セッション設定エラー', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'セッション設定に失敗しました'], 500);
        }
    }
    
    /**
     * 顧客のサブスクリプション情報を取得（トークンベース）
     */
    public function getSubscriptions(Request $request)
    {
        $token = str_replace('Bearer ', '', $request->header('Authorization'));
        
        if (!$token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $customer = null;
        
        // Sanctumトークンかどうかをチェック（パイプが含まれている場合）
        if (strpos($token, '|') !== false) {
            // Sanctumトークンの場合
            $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if ($personalAccessToken) {
                $customer = $personalAccessToken->tokenable;
            }
        } else {
            // Base64エンコードされた顧客データの場合（従来の方式）
            $customerData = json_decode(base64_decode($token), true);
            $customerId = $customerData['id'] ?? null;
            
            if (!$customerId) {
                // トークンから取得できない場合、電話番号で検索（テスト用）
                $phone = $customerData['phone'] ?? null;
                if ($phone) {
                    $customer = Customer::where('phone', $phone)->first();
                }
            } else {
                $customer = Customer::find($customerId);
            }
        }
        
        if (!$customer) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
        
        // アクティブなサブスクリプションを取得
        $subscriptions = CustomerSubscription::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->with(['store'])
            ->get();
            
        return response()->json([
            'data' => $subscriptions->map(function ($sub) {
                // current_month_visitsは自動計算されるため、リセット処理不要

                $remaining = $sub->monthly_limit ?
                    max(0, $sub->monthly_limit - $sub->current_month_visits) :
                    ($sub->remaining_sessions ?? 0);
                    
                // メニュー情報を取得
                $menu = null;
                if ($sub->menu_id) {
                    $menu = \App\Models\Menu::find($sub->menu_id);
                } elseif ($sub->plan_id) {
                    $plan = \App\Models\SubscriptionPlan::find($sub->plan_id);
                    if ($plan && $plan->menu_id) {
                        $menu = \App\Models\Menu::find($plan->menu_id);
                    }
                }
                    
                return [
                    'id' => $sub->id,
                    'status' => $sub->status,
                    'is_paused' => $sub->is_paused,
                    'pause_start_date' => $sub->pause_start_date?->format('Y-m-d'),
                    'pause_end_date' => $sub->pause_end_date?->format('Y-m-d'),
                    'store_id' => $sub->store_id,
                    'menu_id' => $sub->menu_id ?? ($menu ? $menu->id : null),
                    'plan_name' => $sub->plan_name ?? 'プラン',
                    'monthly_price' => $sub->monthly_price,
                    'plan' => [
                        'id' => $sub->plan_id,
                        'name' => $sub->plan_name ?? 'プラン',
                        'menu_id' => $menu ? $menu->id : null,
                    ],
                    'menu' => $menu ? [
                        'id' => $menu->id,
                        'name' => $menu->name,
                        'duration' => $menu->duration,
                        'price' => $menu->price,
                    ] : null,
                    'remaining_sessions' => $remaining,
                    'monthly_limit' => $sub->monthly_limit,
                    'current_month_visits' => $sub->current_month_visits,
                    // 期間情報を追加
                    'period_start' => $sub->getCurrentPeriodStart()?->format('Y-m-d'),
                    'period_end' => $sub->getCurrentPeriodEnd()?->format('Y-m-d'),
                    'next_reset_date' => $sub->getNextResetDate()?->format('Y-m-d'),
                    'days_until_reset' => $sub->getDaysUntilReset(),
                    'store' => $sub->store ? [
                        'id' => $sub->store->id,
                        'name' => $sub->store->name,
                    ] : null,
                ];
            })
        ]);
    }

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