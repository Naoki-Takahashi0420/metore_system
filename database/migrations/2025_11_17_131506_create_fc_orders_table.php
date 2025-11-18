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
        Schema::create('fc_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // 発注番号（ORD-YYYYMMDD-XXXX形式）
            $table->foreignId('fc_store_id')->constrained('stores')->cascadeOnDelete(); // 発注元FC店舗
            $table->foreignId('headquarters_store_id')->constrained('stores')->cascadeOnDelete(); // 発注先本部
            $table->string('status')->default('draft'); // draft, pending, approved, processing, shipped, delivered, cancelled
            $table->decimal('subtotal', 12, 2)->default(0); // 小計（税抜）
            $table->decimal('tax_amount', 12, 2)->default(0); // 消費税額
            $table->decimal('total_amount', 12, 2)->default(0); // 合計（税込）
            $table->text('notes')->nullable(); // 備考
            $table->text('rejection_reason')->nullable(); // 却下理由
            $table->timestamp('ordered_at')->nullable(); // 発注日時
            $table->timestamp('approved_at')->nullable(); // 承認日時
            $table->timestamp('shipped_at')->nullable(); // 発送日時
            $table->timestamp('delivered_at')->nullable(); // 納品日時
            $table->string('shipping_tracking_number')->nullable(); // 追跡番号
            $table->timestamps();

            $table->index(['fc_store_id', 'status']);
            $table->index(['headquarters_store_id', 'status']);
            $table->index('order_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fc_orders');
    }
};
