<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ReservationController extends Controller
{
    /**
     * サブスク予約の開始
     */
    public function startSubscriptionBooking(Request $request)
    {
        $customerId = $request->input('customer_id');
        $customer = Customer::find($customerId);
        
        if (!$customer) {
            return response()->json(['error' => '顧客情報が見つかりません'], 404);
        }
        
        // アクティブなサブスクを取得
        $subscription = CustomerSubscription::where('customer_id', $customerId)
            ->where('status', 'active')
            ->where('payment_failed', false)
            ->where('is_paused', false)
            ->first();
            
        if (!$subscription) {
            return response()->json(['error' => 'アクティブなサブスクリプションが見つかりません'], 404);
        }
        
        // セッションに保存（サーバーサイド）
        Session::put('reservation_context', [
            'type' => 'subscription',
            'customer_id' => $customerId,
            'subscription_id' => $subscription->id,
            'store_id' => $subscription->store_id,
            'menu_id' => $subscription->menu_id,
            'remaining_visits' => $subscription->getRemainingVisitsAttribute(),
        ]);
        
        return response()->json([
            'success' => true,
            'redirect_url' => '/reservation/stores',
            'subscription' => [
                'store_id' => $subscription->store_id,
                'store_name' => $subscription->store->name ?? null,
                'menu_id' => $subscription->menu_id,
                'plan_name' => $subscription->plan_name,
            ]
        ]);
    }
    
    /**
     * 店舗選択画面
     */
    public function showStores()
    {
        // 有効な店舗のみ取得（is_active = true かつ status = 'active'）
        $stores = Store::where('is_active', true)
            ->where('status', 'active')
            ->get();
        $context = Session::get('reservation_context');
        
        return view('reservation.stores', [
            'stores' => $stores,
            'reservation_context' => $context,
            'is_subscription' => $context && $context['type'] === 'subscription',
            'subscription_store_id' => $context['store_id'] ?? null,
        ]);
    }
    
    /**
     * 店舗選択処理
     */
    public function selectStore(Request $request)
    {
        $storeId = $request->input('store_id');
        $context = Session::get('reservation_context', []);
        
        // サブスク予約で異なる店舗を選択した場合
        if (isset($context['type']) && $context['type'] === 'subscription') {
            if ($storeId != $context['store_id']) {
                // 通常予約に切り替え
                Session::put('reservation_context', [
                    'type' => 'regular',
                    'customer_id' => $context['customer_id'] ?? null,
                    'store_id' => $storeId,
                    'original_subscription_id' => $context['subscription_id'] ?? null,
                ]);
                
                return redirect('/reservation/menu')
                    ->with('warning', 'サブスク契約店舗と異なるため、通常料金での予約となります。');
            }
        }
        
        // 店舗情報を保存
        $context['selected_store_id'] = $storeId;
        Session::put('reservation_context', $context);
        
        return redirect('/reservation/menu');
    }
    
    /**
     * 予約確定処理
     */
    public function confirmReservation(Request $request)
    {
        $context = Session::get('reservation_context');
        
        // バリデーション
        if (!$context) {
            return response()->json(['error' => '予約情報が見つかりません'], 400);
        }
        
        // サブスク予約の場合
        if ($context['type'] === 'subscription') {
            $subscription = CustomerSubscription::find($context['subscription_id']);
            
            if (!$subscription) {
                return response()->json(['error' => 'サブスクリプションが見つかりません'], 404);
            }
            
            // 利用回数チェック
            if ($subscription->hasReachedLimit()) {
                return response()->json(['error' => '今月の利用上限に達しています'], 400);
            }
            
            // 予約作成（料金0円）
            $reservation = Reservation::create([
                'customer_id' => $context['customer_id'],
                'store_id' => $context['store_id'],
                'menu_id' => $context['menu_id'],
                'price' => 0, // サブスク利用
                'is_subscription' => true,
                'subscription_id' => $subscription->id,
                // ... その他のフィールド
            ]);
            
            // 利用回数を記録
            $subscription->recordVisit();
            
        } else {
            // 通常予約の処理
            $menu = Menu::find($request->input('menu_id'));
            
            $reservation = Reservation::create([
                'customer_id' => $context['customer_id'] ?? null,
                'store_id' => $context['selected_store_id'],
                'menu_id' => $menu->id,
                'price' => $menu->price,
                'is_subscription' => false,
                // ... その他のフィールド
            ]);
        }
        
        // セッションクリア
        Session::forget('reservation_context');
        
        return response()->json([
            'success' => true,
            'reservation_id' => $reservation->id,
        ]);
    }
}