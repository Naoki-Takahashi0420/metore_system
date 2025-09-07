<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;

class TestSubscriptionController extends Controller
{
    public function getByPhone($phone)
    {
        $customer = Customer::where('phone', $phone)->first();
        
        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }
        
        $subscription = $customer->activeSubscription()->with(['store'])->first();
        
        if (!$subscription) {
            return response()->json(['subscription' => null]);
        }
        
        // メニュー情報も取得
        $menu = null;
        if ($subscription->menu_id) {
            $menu = \App\Models\Menu::find($subscription->menu_id);
        }
        
        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->last_name . ' ' . $customer->first_name,
                'phone' => $customer->phone,
            ],
            'subscription' => [
                'id' => $subscription->id,
                'store_id' => $subscription->store_id,
                'menu_id' => $subscription->menu_id,
                'menu_name' => $menu ? $menu->name : $subscription->plan_name,
                'plan_name' => $subscription->plan_name,
                'monthly_price' => $subscription->monthly_price,
                'monthly_limit' => $subscription->monthly_limit,
                'current_month_visits' => $subscription->current_month_visits,
                'billing_start_date' => $subscription->billing_start_date?->format('Y-m-d'),
                'service_start_date' => $subscription->service_start_date?->format('Y-m-d'),
                'end_date' => $subscription->end_date?->format('Y-m-d'),
                'status' => $subscription->status,
                'store' => $subscription->store ? [
                    'id' => $subscription->store->id,
                    'name' => $subscription->store->name,
                ] : null,
            ]
        ]);
    }
}