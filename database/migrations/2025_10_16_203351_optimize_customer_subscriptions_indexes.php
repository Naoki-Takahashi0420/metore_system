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
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            // 店舗切り替え時の最適化
            // WHERE customer_id = ? AND status = ? AND store_id = ? に最適
            $table->index(['customer_id', 'status', 'store_id'], 'idx_customer_status_store');

            // 店舗別のサブスク一覧取得の最適化
            // WHERE store_id = ? AND status = ? に最適
            $table->index(['store_id', 'status', 'customer_id'], 'idx_store_status_customer');
        });

        // 回数券テーブルも最適化
        Schema::table('customer_tickets', function (Blueprint $table) {
            // 店舗切り替え時の最適化
            // WHERE customer_id = ? AND store_id = ? に最適
            $table->index(['customer_id', 'store_id', 'status'], 'idx_customer_store_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_customer_status_store');
            $table->dropIndex('idx_store_status_customer');
        });

        Schema::table('customer_tickets', function (Blueprint $table) {
            $table->dropIndex('idx_customer_store_status');
        });
    }
};
