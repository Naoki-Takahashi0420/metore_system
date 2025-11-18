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
        Schema::create('fc_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fc_order_id')->constrained('fc_orders')->cascadeOnDelete();
            $table->foreignId('fc_product_id')->constrained('fc_products')->cascadeOnDelete();
            $table->string('product_name'); // 発注時点の商品名（スナップショット）
            $table->string('product_sku'); // 発注時点のSKU
            $table->integer('quantity'); // 数量
            $table->decimal('unit_price', 10, 2); // 発注時点の単価（税抜）
            $table->decimal('tax_rate', 5, 2); // 発注時点の税率
            $table->decimal('subtotal', 12, 2); // 小計（税抜）
            $table->decimal('tax_amount', 12, 2); // 消費税額
            $table->decimal('total', 12, 2); // 合計（税込）
            $table->timestamps();

            $table->index('fc_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fc_order_items');
    }
};
