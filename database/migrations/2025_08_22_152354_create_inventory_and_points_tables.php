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
        // 商品マスタテーブル（物販用）
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique()->comment('商品コード');
            $table->string('name')->comment('商品名');
            $table->text('description')->nullable()->comment('商品説明');
            $table->enum('category', ['supplement', 'eyewear', 'accessory', 'book', 'other'])->comment('カテゴリ');
            $table->decimal('price', 10, 2)->comment('販売価格');
            $table->decimal('cost', 10, 2)->default(0)->comment('原価');
            $table->string('unit')->default('個')->comment('単位');
            $table->string('barcode')->nullable()->comment('バーコード');
            $table->boolean('is_active')->default(true)->comment('販売中フラグ');
            $table->json('images')->nullable()->comment('商品画像');
            $table->timestamps();
            
            $table->index('product_code');
            $table->index('category');
        });
        
        // 在庫テーブル
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->comment('商品ID');
            $table->foreignId('store_id')->constrained()->comment('店舗ID');
            $table->integer('quantity')->default(0)->comment('在庫数');
            $table->integer('min_quantity')->default(0)->comment('最小在庫数');
            $table->integer('max_quantity')->nullable()->comment('最大在庫数');
            $table->decimal('last_purchase_price', 10, 2)->nullable()->comment('最終仕入価格');
            $table->date('last_purchase_date')->nullable()->comment('最終仕入日');
            $table->date('last_sale_date')->nullable()->comment('最終販売日');
            $table->timestamps();
            
            $table->unique(['product_id', 'store_id']);
            $table->index('quantity');
        });
        
        // 在庫移動履歴テーブル
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->comment('在庫ID');
            $table->enum('type', ['purchase', 'sale', 'adjustment', 'transfer', 'loss', 'return'])->comment('取引種別');
            $table->integer('quantity')->comment('数量（+/-）');
            $table->integer('balance_after')->comment('取引後在庫');
            $table->decimal('unit_price', 10, 2)->nullable()->comment('単価');
            $table->decimal('total_amount', 10, 2)->nullable()->comment('合計金額');
            $table->foreignId('sale_id')->nullable()->constrained()->comment('売上ID');
            $table->foreignId('user_id')->nullable()->constrained()->comment('処理者ID');
            $table->string('reference_number')->nullable()->comment('参照番号');
            $table->text('notes')->nullable()->comment('備考');
            $table->timestamps();
            
            $table->index(['inventory_id', 'created_at']);
            $table->index('type');
        });
        
        // ポイントカードテーブル
        Schema::create('point_cards', function (Blueprint $table) {
            $table->id();
            $table->string('card_number')->unique()->comment('カード番号');
            $table->foreignId('customer_id')->constrained()->comment('顧客ID');
            $table->integer('total_points')->default(0)->comment('総ポイント');
            $table->integer('available_points')->default(0)->comment('利用可能ポイント');
            $table->integer('used_points')->default(0)->comment('使用済みポイント');
            $table->integer('expired_points')->default(0)->comment('失効ポイント');
            $table->enum('status', ['active', 'suspended', 'expired'])->default('active')->comment('ステータス');
            $table->date('issued_date')->comment('発行日');
            $table->date('last_used_date')->nullable()->comment('最終利用日');
            $table->date('expiry_date')->nullable()->comment('有効期限');
            $table->timestamps();
            
            $table->index('card_number');
            $table->index('customer_id');
        });
        
        // ポイント履歴テーブル
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_card_id')->constrained()->comment('ポイントカードID');
            $table->enum('type', ['earned', 'used', 'expired', 'adjusted', 'bonus'])->comment('取引種別');
            $table->integer('points')->comment('ポイント数（+/-）');
            $table->integer('balance_after')->comment('取引後残高');
            $table->foreignId('sale_id')->nullable()->constrained()->comment('売上ID');
            $table->foreignId('reservation_id')->nullable()->constrained()->comment('予約ID');
            $table->string('description')->comment('説明');
            $table->date('expiry_date')->nullable()->comment('ポイント有効期限');
            $table->timestamps();
            
            $table->index(['point_card_id', 'created_at']);
            $table->index('type');
        });
        
        // ポイント設定テーブル
        Schema::create('point_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->comment('店舗ID（nullは全店共通）');
            $table->decimal('points_per_yen', 5, 2)->default(1)->comment('1円あたりのポイント');
            $table->decimal('yen_per_point', 5, 2)->default(1)->comment('1ポイントあたりの円');
            $table->integer('minimum_purchase')->default(0)->comment('ポイント付与最低金額');
            $table->integer('minimum_points_to_use')->default(1)->comment('最低利用ポイント');
            $table->integer('maximum_points_per_use')->nullable()->comment('1回の最大利用ポイント');
            $table->integer('point_validity_days')->default(365)->comment('ポイント有効日数');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->json('bonus_rules')->nullable()->comment('ボーナスルール');
            $table->timestamps();
            
            $table->index('store_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_settings');
        Schema::dropIfExists('point_transactions');
        Schema::dropIfExists('point_cards');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('products');
    }
};