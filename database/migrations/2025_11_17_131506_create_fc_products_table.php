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
        Schema::create('fc_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('headquarters_store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('fc_product_categories')->nullOnDelete();
            $table->string('sku')->unique(); // 商品コード
            $table->string('name'); // 商品名
            $table->string('image_path')->nullable(); // 商品画像
            $table->text('description')->nullable(); // 商品説明
            $table->decimal('unit_price', 10, 2); // 卸価格（税抜）
            $table->decimal('tax_rate', 5, 2)->default(10.00); // 税率
            $table->string('unit')->default('個'); // 単位（個、箱、セットなど）
            $table->integer('stock_quantity')->default(0); // 在庫数
            $table->integer('min_order_quantity')->default(1); // 最小発注数
            $table->boolean('is_active')->default(true); // 販売中フラグ
            $table->timestamps();

            $table->index(['headquarters_store_id', 'is_active']);
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fc_products');
    }
};
