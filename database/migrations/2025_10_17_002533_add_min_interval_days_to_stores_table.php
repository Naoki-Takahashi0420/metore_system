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
        Schema::table('stores', function (Blueprint $table) {
            // 最小予約間隔日数（デフォルト5日）
            // 既存顧客が前回予約から何日空けないと次の予約ができないか
            $table->integer('min_interval_days')->default(5)->after('max_advance_days')->comment('最小予約間隔日数');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('min_interval_days');
        });
    }
};
