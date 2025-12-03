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
        Schema::table('fc_order_items', function (Blueprint $table) {
            // 部分発送対応
            $table->integer('shipped_quantity')->default(0)->after('quantity'); // 発送済み数量
            $table->string('shipping_status', 20)->default('pending')->after('shipped_quantity'); 
            // 'pending': 未発送, 'partial': 部分発送, 'completed': 発送完了
            
            // インデックス
            $table->index(['fc_order_id', 'shipping_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fc_order_items', function (Blueprint $table) {
            $table->dropIndex(['fc_order_id', 'shipping_status']);
            $table->dropColumn(['shipped_quantity', 'shipping_status']);
        });
    }
};