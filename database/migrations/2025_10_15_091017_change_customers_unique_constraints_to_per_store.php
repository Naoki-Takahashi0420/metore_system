<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 目的: 同じ電話番号・メールアドレスを異なる店舗で登録可能にする
     * 変更: UNIQUE(phone) → UNIQUE(store_id, phone)
     *       UNIQUE(email) → UNIQUE(store_id, email)
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // 既存のグローバルUNIQUE制約を削除
            $table->dropUnique('customers_phone_unique');
            $table->dropUnique('customers_email_unique');

            // 店舗ごとのUNIQUE制約を追加
            $table->unique(['store_id', 'phone'], 'customers_store_phone_unique');
            $table->unique(['store_id', 'email'], 'customers_store_email_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * ロールバック時の動作:
     * 1. 店舗ごとのUNIQUE制約を削除
     * 2. グローバルUNIQUE制約を復元
     *
     * 注意: 複数店舗に同じ電話番号が存在する場合、ロールバックは失敗します
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // 店舗ごとのUNIQUE制約を削除
            $table->dropUnique('customers_store_phone_unique');
            $table->dropUnique('customers_store_email_unique');

            // グローバルUNIQUE制約を復元
            $table->unique('phone', 'customers_phone_unique');
            $table->unique('email', 'customers_email_unique');
        });
    }
};
