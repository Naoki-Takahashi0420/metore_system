<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * 新規サブスクリプション登録
     */
    public function subscribe(Customer $customer, SubscriptionPlan $plan, array $options = []): CustomerSubscription
    {
        return DB::transaction(function () use ($customer, $plan, $options) {
            // 既存のアクティブなサブスクリプションをキャンセル
            $customer->subscriptions()
                ->where('status', 'active')
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            
            // 新しいサブスクリプションを作成
            $subscription = CustomerSubscription::create([
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'started_at' => $options['started_at'] ?? now(),
                'expires_at' => $options['expires_at'] ?? now()->addDays($plan->duration_days),
                'auto_renew' => $options['auto_renew'] ?? true,
                'payment_method' => $options['payment_method'] ?? 'credit_card',
                'metadata' => $options['metadata'] ?? null,
            ]);
            
            // 初回支払い記録
            $this->createPayment($subscription, $plan->price, 'completed');
            
            Log::info('サブスクリプション登録', [
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'subscription_id' => $subscription->id,
            ]);
            
            return $subscription;
        });
    }
    
    /**
     * サブスクリプション更新
     */
    public function renew(CustomerSubscription $subscription): bool
    {
        if ($subscription->status !== 'active' || !$subscription->auto_renew) {
            return false;
        }
        
        return DB::transaction(function () use ($subscription) {
            $plan = $subscription->plan;
            
            // 期限を延長
            $subscription->update([
                'expires_at' => $subscription->expires_at->addDays($plan->duration_days),
                'renewed_at' => now(),
            ]);
            
            // 支払い処理
            $payment = $this->createPayment($subscription, $plan->price);
            
            // 自動課金処理（実際の決済はここで行う）
            $paymentResult = $this->processPayment($payment);
            
            if ($paymentResult) {
                $payment->update(['status' => 'completed']);
                
                Log::info('サブスクリプション更新成功', [
                    'subscription_id' => $subscription->id,
                    'payment_id' => $payment->id,
                ]);
                
                return true;
            } else {
                $payment->update(['status' => 'failed']);
                $subscription->update(['status' => 'payment_failed']);
                
                Log::error('サブスクリプション更新失敗', [
                    'subscription_id' => $subscription->id,
                    'payment_id' => $payment->id,
                ]);
                
                return false;
            }
        });
    }
    
    /**
     * サブスクリプションキャンセル
     */
    public function cancel(CustomerSubscription $subscription, string $reason = null): bool
    {
        if (!in_array($subscription->status, ['active', 'payment_failed'])) {
            return false;
        }
        
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'auto_renew' => false,
        ]);
        
        Log::info('サブスクリプションキャンセル', [
            'subscription_id' => $subscription->id,
            'reason' => $reason,
        ]);
        
        return true;
    }
    
    /**
     * 支払い記録作成
     */
    protected function createPayment(CustomerSubscription $subscription, int $amount, string $status = 'pending'): SubscriptionPayment
    {
        return SubscriptionPayment::create([
            'customer_subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer_id,
            'amount' => $amount,
            'payment_method' => $subscription->payment_method,
            'status' => $status,
            'payment_date' => now(),
            'due_date' => now()->addDays(7),
        ]);
    }
    
    /**
     * 決済処理（実際の決済ゲートウェイとの連携）
     */
    protected function processPayment(SubscriptionPayment $payment): bool
    {
        // TODO: Stripe/PayPay等の決済ゲートウェイとの連携実装
        // 現在はダミー実装
        
        try {
            // 決済ゲートウェイAPIコール
            // $result = PaymentGateway::charge([
            //     'amount' => $payment->amount,
            //     'customer_id' => $payment->customer_id,
            //     'method' => $payment->payment_method,
            // ]);
            
            // ダミー成功
            $payment->update([
                'transaction_id' => 'TXN_' . uniqid(),
                'payment_details' => [
                    'gateway' => 'dummy',
                    'processed_at' => now()->toIso8601String(),
                ],
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('決済処理エラー', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    /**
     * 期限切れサブスクリプションの自動更新処理
     */
    public function processAutoRenewals(): int
    {
        $count = 0;
        
        $expiring = CustomerSubscription::where('status', 'active')
            ->where('auto_renew', true)
            ->where('expires_at', '<=', now()->addDays(1))
            ->get();
        
        foreach ($expiring as $subscription) {
            if ($this->renew($subscription)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * 期限切れサブスクリプションの無効化
     */
    public function deactivateExpired(): int
    {
        return CustomerSubscription::where('status', 'active')
            ->where('auto_renew', false)
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }
}