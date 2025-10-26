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
        Schema::table('medical_records', function (Blueprint $table) {
            // store_idカラムを追加（既存データがあるのでnullable）
            $table->unsignedBigInteger('store_id')->nullable()->after('id');

            // 外部キー制約を追加
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('set null');

            // インデックスを追加（検索パフォーマンス向上）
            $table->index('store_id');
        });

        // 既存のカルテレコードにstore_idを設定
        // 優先順位: 1. 予約の店舗 2. 顧客の店舗
        \DB::statement('
            UPDATE medical_records
            SET store_id = (
                CASE
                    WHEN reservation_id IS NOT NULL THEN (
                        SELECT store_id FROM reservations WHERE reservations.id = medical_records.reservation_id LIMIT 1
                    )
                    ELSE (
                        SELECT store_id FROM customers WHERE customers.id = medical_records.customer_id LIMIT 1
                    )
                END
            )
            WHERE store_id IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            // 外部キー制約を削除
            $table->dropForeign(['store_id']);

            // インデックスを削除
            $table->dropIndex(['store_id']);

            // カラムを削除
            $table->dropColumn('store_id');
        });
    }
};
