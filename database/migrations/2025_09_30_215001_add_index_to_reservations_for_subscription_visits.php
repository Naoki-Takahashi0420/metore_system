<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * サブスク利用回数の動的計算を高速化するためのインデックス
     * 予約テーブルから特定顧客の特定期間の予約をカウントする際に使用
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // 複合インデックス: customer_id + reservation_date + status
            // getCurrentPeriodVisitsCount()で使用されるクエリを最適化
            $table->index(['customer_id', 'reservation_date', 'status'], 'idx_reservations_customer_date_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('idx_reservations_customer_date_status');
        });
    }
};
