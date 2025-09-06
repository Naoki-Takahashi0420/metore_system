<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\SubscriptionService;
use App\Models\Customer;
use App\Models\SubscriptionPlan;
use App\Models\CustomerSubscription;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;
    
    private SubscriptionService $service;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SubscriptionService();
    }
    
    public function test_新規サブスクリプション登録()
    {
        // Arrange
        $customer = Customer::factory()->create();
        $plan = SubscriptionPlan::create([
            'name' => 'ベーシックプラン',
            'code' => 'BASIC_PLAN',
            'price' => 5000,
            'contract_months' => 1,
            'is_active' => true,
        ]);
        
        // Act
        $subscription = $this->service->subscribe($customer, $plan);
        
        // Assert
        $this->assertDatabaseHas('customer_subscriptions', [
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        
        $this->assertEquals($customer->id, $subscription->customer_id);
        $this->assertEquals($plan->id, $subscription->plan_id);
        $this->assertEquals('active', $subscription->status);
        $this->assertTrue($subscription->expires_at->isAfter(now()));
    }
    
    public function test_既存サブスクリプションがある場合は自動キャンセル()
    {
        // Arrange
        $customer = Customer::factory()->create();
        $oldPlan = SubscriptionPlan::create([
            'name' => '旧プラン',
            'code' => 'OLD_PLAN',
            'price' => 3000,
            'contract_months' => 1,
            'is_active' => true,
        ]);
        $newPlan = SubscriptionPlan::create([
            'name' => '新プラン',
            'code' => 'NEW_PLAN',
            'price' => 5000,
            'contract_months' => 1,
            'is_active' => true,
        ]);
        
        // 既存のサブスクリプション作成
        $oldSubscription = $this->service->subscribe($customer, $oldPlan);
        
        // Act
        $newSubscription = $this->service->subscribe($customer, $newPlan);
        
        // Assert
        $oldSubscription->refresh();
        $this->assertEquals('cancelled', $oldSubscription->status);
        $this->assertEquals('active', $newSubscription->status);
    }
    
    public function test_サブスクリプション自動更新()
    {
        // Arrange
        $customer = Customer::factory()->create();
        $plan = SubscriptionPlan::create([
            'name' => '自動更新プラン',
            'code' => 'AUTO_RENEW_PLAN',
            'price' => 5000,
            'contract_months' => 1,
            'is_active' => true,
        ]);
        
        $subscription = CustomerSubscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => now()->subDays(29),
            'expires_at' => now()->addDay(),
            'auto_renew' => true,
            'payment_method' => 'credit_card',
        ]);
        
        // Act
        $result = $this->service->renew($subscription);
        
        // Assert
        $this->assertTrue($result);
        $subscription->refresh();
        $this->assertTrue($subscription->expires_at->isAfter(now()->addDays(29)));
        
        // 支払い記録確認
        $this->assertDatabaseHas('subscription_payments', [
            'customer_subscription_id' => $subscription->id,
            'amount' => $plan->price,
            'status' => 'completed',
        ]);
    }
    
    public function test_自動更新オフの場合は更新されない()
    {
        // Arrange
        $customer = Customer::factory()->create();
        $plan = SubscriptionPlan::create([
            'name' => '手動更新プラン',
            'code' => 'MANUAL_RENEW_PLAN',
            'price' => 5000,
            'contract_months' => 1,
            'is_active' => true,
        ]);
        
        $subscription = CustomerSubscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => now()->subDays(29),
            'expires_at' => now()->addDay(),
            'auto_renew' => false,
            'payment_method' => 'credit_card',
        ]);
        
        // Act
        $result = $this->service->renew($subscription);
        
        // Assert
        $this->assertFalse($result);
    }
    
    public function test_サブスクリプションキャンセル()
    {
        // Arrange
        $customer = Customer::factory()->create();
        $plan = SubscriptionPlan::create([
            'name' => 'キャンセルテストプラン',
            'code' => 'CANCEL_TEST_PLAN',
            'price' => 5000,
            'contract_months' => 1,
            'is_active' => true,
        ]);
        
        $subscription = $this->service->subscribe($customer, $plan);
        
        // Act
        $result = $this->service->cancel($subscription, 'ユーザー都合');
        
        // Assert
        $this->assertTrue($result);
        $subscription->refresh();
        $this->assertEquals('cancelled', $subscription->status);
        $this->assertEquals('ユーザー都合', $subscription->cancellation_reason);
        $this->assertFalse($subscription->auto_renew);
        $this->assertNotNull($subscription->cancelled_at);
    }
    
    public function test_期限切れサブスクリプションの無効化()
    {
        // Arrange
        $customer = Customer::factory()->create();
        $plan = SubscriptionPlan::create([
            'name' => '期限切れテストプラン',
            'code' => 'EXPIRED_TEST_PLAN',
            'price' => 5000,
            'contract_months' => 1,
            'is_active' => true,
        ]);
        
        // 期限切れサブスクリプション作成
        $expiredSubscription = CustomerSubscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => now()->subDays(31),
            'expires_at' => now()->subDay(),
            'auto_renew' => false,
            'payment_method' => 'credit_card',
        ]);
        
        // まだ有効なサブスクリプション
        $validSubscription = CustomerSubscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => now()->subDays(15),
            'expires_at' => now()->addDays(15),
            'auto_renew' => false,
            'payment_method' => 'credit_card',
        ]);
        
        // Act
        $count = $this->service->deactivateExpired();
        
        // Assert
        $this->assertEquals(1, $count);
        
        $expiredSubscription->refresh();
        $validSubscription->refresh();
        
        $this->assertEquals('expired', $expiredSubscription->status);
        $this->assertEquals('active', $validSubscription->status);
    }
    
    public function test_一括自動更新処理()
    {
        // Arrange
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        $plan = SubscriptionPlan::create([
            'name' => '一括更新テストプラン',
            'code' => 'BATCH_UPDATE_PLAN',
            'price' => 5000,
            'contract_months' => 1,
            'is_active' => true,
        ]);
        
        // 更新対象のサブスクリプション
        $subscription1 = CustomerSubscription::create([
            'customer_id' => $customer1->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => now()->subDays(30),
            'expires_at' => now()->addHours(12),
            'auto_renew' => true,
            'payment_method' => 'credit_card',
        ]);
        
        $subscription2 = CustomerSubscription::create([
            'customer_id' => $customer2->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => now()->subDays(30),
            'expires_at' => now()->addHours(20),
            'auto_renew' => true,
            'payment_method' => 'credit_card',
        ]);
        
        // 更新対象外（自動更新オフ）
        $subscription3 = CustomerSubscription::create([
            'customer_id' => $customer1->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => now()->subDays(30),
            'expires_at' => now()->addHours(10),
            'auto_renew' => false,
            'payment_method' => 'credit_card',
        ]);
        
        // Act
        $count = $this->service->processAutoRenewals();
        
        // Assert
        $this->assertEquals(2, $count);
        
        $subscription1->refresh();
        $subscription2->refresh();
        $subscription3->refresh();
        
        $this->assertTrue($subscription1->expires_at->isAfter(now()->addDays(29)));
        $this->assertTrue($subscription2->expires_at->isAfter(now()->addDays(29)));
        $this->assertTrue($subscription3->expires_at->isBefore(now()->addDay()));
    }
}