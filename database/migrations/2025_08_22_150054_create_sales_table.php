<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 売上テーブル
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique()->comment('売上番号');
            $table->foreignId('reservation_id')->nullable()->constrained()->comment('予約ID');
            $table->foreignId('customer_id')->nullable()->constrained()->comment('顧客ID');
            $table->foreignId('store_id')->constrained()->comment('店舗ID');
            $table->foreignId('staff_id')->nullable()->constrained('users')->comment('担当スタッフID');
            $table->date('sale_date')->comment('売上日');
            $table->time('sale_time')->comment('売上時刻');
            $table->decimal('subtotal', 10, 2)->default(0)->comment('小計');
            $table->decimal('tax_amount', 10, 2)->default(0)->comment('消費税額');
            $table->decimal('discount_amount', 10, 2)->default(0)->comment('割引額');
            $table->decimal('total_amount', 10, 2)->comment('合計金額');
            $table->enum('payment_method', ['cash', 'credit_card', 'debit_card', 'paypay', 'line_pay', 'other'])->comment('支払方法');
            $table->string('receipt_number')->nullable()->comment('レシート番号');
            $table->enum('status', ['completed', 'cancelled', 'refunded', 'partial_refund'])->default('completed')->comment('ステータス');
            $table->text('notes')->nullable()->comment('備考');
            $table->timestamps();
            
            // インデックス
            $table->index(['store_id', 'sale_date']);
            $table->index(['customer_id']);
            $table->index(['staff_id']);
        });
        
        // 売上明細テーブル
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->onDelete('cascade')->comment('売上ID');
            $table->foreignId('menu_id')->nullable()->constrained()->comment('メニューID');
            $table->string('item_type')->default('service')->comment('商品タイプ（service/product/other）');
            $table->string('item_name')->comment('商品名');
            $table->text('item_description')->nullable()->comment('商品説明');
            $table->decimal('unit_price', 10, 2)->comment('単価');
            $table->integer('quantity')->default(1)->comment('数量');
            $table->decimal('discount_amount', 10, 2)->default(0)->comment('割引額');
            $table->decimal('tax_rate', 5, 2)->default(10)->comment('税率');
            $table->decimal('tax_amount', 10, 2)->comment('税額');
            $table->decimal('amount', 10, 2)->comment('金額');
            $table->timestamps();
            
            // インデックス
            $table->index('sale_id');
            $table->index('menu_id');
        });
        
        // 日次精算テーブル
        Schema::create('daily_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->comment('店舗ID');
            $table->date('closing_date')->comment('精算日');
            $table->time('open_time')->nullable()->comment('開店時刻');
            $table->time('close_time')->nullable()->comment('閉店時刻');
            $table->decimal('opening_cash', 10, 2)->default(0)->comment('釣銭準備金');
            $table->decimal('cash_sales', 10, 2)->default(0)->comment('現金売上');
            $table->decimal('card_sales', 10, 2)->default(0)->comment('カード売上');
            $table->decimal('digital_sales', 10, 2)->default(0)->comment('電子マネー売上');
            $table->decimal('total_sales', 10, 2)->default(0)->comment('総売上');
            $table->decimal('expected_cash', 10, 2)->default(0)->comment('予定現金残高');
            $table->decimal('actual_cash', 10, 2)->default(0)->comment('実際現金残高');
            $table->decimal('cash_difference', 10, 2)->default(0)->comment('現金差異');
            $table->integer('transaction_count')->default(0)->comment('取引件数');
            $table->integer('customer_count')->default(0)->comment('客数');
            $table->json('sales_by_staff')->nullable()->comment('スタッフ別売上');
            $table->json('sales_by_menu')->nullable()->comment('メニュー別売上');
            $table->enum('status', ['open', 'closed', 'verified'])->default('open')->comment('ステータス');
            $table->foreignId('closed_by')->nullable()->constrained('users')->comment('締め処理者');
            $table->foreignId('verified_by')->nullable()->constrained('users')->comment('承認者');
            $table->timestamp('closed_at')->nullable()->comment('締め処理日時');
            $table->timestamp('verified_at')->nullable()->comment('承認日時');
            $table->text('notes')->nullable()->comment('備考');
            $table->timestamps();
            
            // インデックス
            $table->unique(['store_id', 'closing_date']);
            $table->index('closing_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_closings');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};