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
        Schema::create('fc_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fc_invoice_id')->constrained()->onDelete('cascade');
            $table->string('type')->default('custom'); // 'product', 'royalty', 'system_fee', 'custom'
            $table->foreignId('fc_product_id')->nullable()->constrained()->onDelete('set null'); // 商品の場合のみ
            $table->string('description'); // 項目名（ロイヤリティ、システム使用料等）
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0); // 値引き額
            $table->decimal('subtotal', 10, 2); // 小計（数量×単価-値引き）
            $table->decimal('tax_rate', 5, 2)->default(10.00); // 税率（％）
            $table->decimal('tax_amount', 10, 2); // 税額
            $table->decimal('total_amount', 10, 2); // 合計（小計+税額）
            $table->text('notes')->nullable(); // 備考
            $table->integer('sort_order')->default(0); // 表示順
            $table->timestamps();
            
            $table->index(['fc_invoice_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fc_invoice_items');
    }
};
