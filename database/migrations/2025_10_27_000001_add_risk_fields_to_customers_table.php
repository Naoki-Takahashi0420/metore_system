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
            // 手動上書きフラグ（trueの場合、自動判定はis_blockedを変更しない）
            $table->boolean('risk_override')->default(false)->after('is_blocked');

            // リスクフラグのソース（'auto' or 'manual'）
            $table->string('risk_flag_source')->nullable()->after('risk_override');

            // 自動判定の根拠（JSON: 閾値・対象予約ID・期間など）
            $table->json('risk_flag_reason')->nullable()->after('risk_flag_source');

            // リスクフラグが設定された日時
            $table->timestamp('risk_flagged_at')->nullable()->after('risk_flag_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'risk_override',
                'risk_flag_source',
                'risk_flag_reason',
                'risk_flagged_at',
            ]);
        });
    }
};
