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
        Schema::table('customers', function (Blueprint $table) {
            // 既存のcustomer_number uniqueインデックスを削除
            $table->dropUnique(['customer_number']);

            // 店舗ごとに顧客番号をユニークにする複合ユニークキーを追加
            $table->unique(['store_id', 'customer_number'], 'customers_store_customer_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // 複合ユニークキーを削除
            $table->dropUnique('customers_store_customer_number_unique');

            // 元のcustomer_number uniqueインデックスを復元
            $table->unique('customer_number');
        });
    }
};
