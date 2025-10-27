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
        Schema::table('reservations', function (Blueprint $table) {
            // キャンセル理由（customer_request / store_fault / system_fix など）
            // 既存環境との互換性のため、カラムが存在しない場合のみ追加
            if (!Schema::hasColumn('reservations', 'cancel_reason')) {
                $table->string('cancel_reason')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // カラムが存在する場合のみ削除
            if (Schema::hasColumn('reservations', 'cancel_reason')) {
                $table->dropColumn('cancel_reason');
            }
        });
    }
};
