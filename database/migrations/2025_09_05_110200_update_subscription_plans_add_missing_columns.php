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
        Schema::table('subscription_plans', function (Blueprint $table) {
            // 新しいカラムを追加（存在しない場合のみ）
            if (!Schema::hasColumn('subscription_plans', 'code')) {
                $table->string('code', 50)->after('name')->nullable();
            }
            if (!Schema::hasColumn('subscription_plans', 'min_contract_months')) {
                $table->integer('min_contract_months')->default(0)->after('max_reservations');
            }
            if (!Schema::hasColumn('subscription_plans', 'max_users')) {
                $table->integer('max_users')->nullable()->after('min_contract_months');
            }
            if (!Schema::hasColumn('subscription_plans', 'notes')) {
                $table->text('notes')->nullable()->after('max_users');
            }
            if (!Schema::hasColumn('subscription_plans', 'duration_days')) {
                $table->integer('duration_days')->default(30)->after('price');
            }
            if (!Schema::hasColumn('subscription_plans', 'max_reservations')) {
                $table->integer('max_reservations')->nullable()->after('duration_days');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // カラムを削除
            if (Schema::hasColumn('subscription_plans', 'code')) {
                $table->dropColumn('code');
            }
            if (Schema::hasColumn('subscription_plans', 'min_contract_months')) {
                $table->dropColumn('min_contract_months');
            }
            if (Schema::hasColumn('subscription_plans', 'max_users')) {
                $table->dropColumn('max_users');
            }
            if (Schema::hasColumn('subscription_plans', 'notes')) {
                $table->dropColumn('notes');
            }
            if (Schema::hasColumn('subscription_plans', 'duration_days')) {
                $table->dropColumn('duration_days');
            }
            if (Schema::hasColumn('subscription_plans', 'max_reservations')) {
                $table->dropColumn('max_reservations');
            }
        });
    }
};