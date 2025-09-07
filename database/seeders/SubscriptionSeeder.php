<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        // サブスクプランを作成
        $plans = [
            [
                'name' => 'ベーシックプラン',
                'description' => '月4回まで予約可能',
                'price' => 9800,
                'contract_months' => 1,
                'max_reservations' => 4,
                'max_users' => 1,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'スタンダードプラン',
                'description' => '月8回まで予約可能',
                'price' => 18000,
                'contract_months' => 1,
                'max_reservations' => 8,
                'max_users' => 1,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'プレミアムプラン',
                'description' => '無制限予約可能',
                'price' => 28000,
                'contract_months' => 1,
                'max_reservations' => 999, // 実質無制限
                'max_users' => 1,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $planData) {
            SubscriptionPlan::create($planData);
        }

        // 既存の顧客からランダムに選んでサブスク契約を作成
        $customers = Customer::inRandomOrder()->limit(10)->get();
        $plans = SubscriptionPlan::all();

        foreach ($customers as $customer) {
            $plan = $plans->random();
            $startDate = Carbon::now()->subDays(rand(0, 60));
            
            CustomerSubscription::create([
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'monthly_price' => $plan->price,
                'monthly_limit' => $plan->max_reservations,
                'start_date' => $startDate,
                'end_date' => $startDate->copy()->addMonths($plan->contract_months),
                'status' => $this->getRandomStatus($startDate),
                'payment_method' => $this->getRandomPaymentMethod(),
                'billing_date' => $startDate->day,
                'billing_start_date' => $startDate,
                'service_start_date' => $startDate,
                'next_billing_date' => $startDate->copy()->addMonth(),
            ]);
        }

        // 期限切れ間近のサブスクを追加
        $expiringCustomers = Customer::inRandomOrder()->limit(3)->get();
        foreach ($expiringCustomers as $customer) {
            $plan = $plans->random();
            $startDate = Carbon::now()->subDays(20);
            
            CustomerSubscription::create([
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'monthly_price' => $plan->price,
                'monthly_limit' => $plan->max_reservations,
                'start_date' => $startDate,
                'end_date' => Carbon::now()->addDays(rand(5, 25)), // 5〜25日後に期限切れ
                'status' => 'active',
                'payment_method' => 'credit_card',
                'billing_date' => $startDate->day,
                'billing_start_date' => $startDate,
                'service_start_date' => $startDate,
                'next_billing_date' => $startDate->copy()->addMonth(),
            ]);
        }
    }

    private function getRandomStatus($startDate): string
    {
        if ($startDate->isFuture()) {
            return 'pending';
        }
        
        return collect(['active', 'active', 'active', 'paused', 'cancelled'])->random();
    }

    private function getRandomPaymentMethod(): string
    {
        return collect(['credit_card', 'bank_transfer', 'cash'])->random();
    }
}