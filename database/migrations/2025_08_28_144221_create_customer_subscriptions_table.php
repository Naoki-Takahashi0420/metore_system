<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('store_id')->nullable()->constrained()->onDelete('set null');
            
            // プラン情報
            $table->string('plan_type'); // 月4回/月8回/無制限等
            $table->string('plan_name');
            $table->integer('monthly_limit')->nullable(); // null = 無制限
            $table->decimal('monthly_price', 10, 2);
            
            // 契約期間
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null = 継続中
            $table->date('next_billing_date')->nullable();
            
            // 支払い情報
            $table->string('payment_method')->default('robopay'); // robopay/credit/bank等
            $table->string('payment_reference')->nullable(); // ロボペイの顧客ID等
            
            // 利用状況
            $table->integer('current_month_visits')->default(0);
            $table->date('last_visit_date')->nullable();
            $table->integer('reset_day')->default(1); // 毎月のリセット日（1-31）
            
            // ステータス
            $table->enum('status', ['active', 'paused', 'cancelled', 'expired'])->default('active');
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // インデックス
            $table->index(['customer_id', 'status']);
            $table->index(['store_id', 'status']);
            $table->index('next_billing_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_subscriptions');
    }
};