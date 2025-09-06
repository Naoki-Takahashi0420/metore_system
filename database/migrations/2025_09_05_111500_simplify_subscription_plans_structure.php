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
            // duration_daysカラムを削除
            if (Schema::hasColumn('subscription_plans', 'duration_days')) {
                $table->dropColumn('duration_days');
            }
            
            // min_contract_monthsをcontract_monthsにリネーム
            if (Schema::hasColumn('subscription_plans', 'min_contract_months')) {
                $table->renameColumn('min_contract_months', 'contract_months');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // contract_monthsをmin_contract_monthsに戻す
            if (Schema::hasColumn('subscription_plans', 'contract_months')) {
                $table->renameColumn('contract_months', 'min_contract_months');
            }
            
            // duration_daysカラムを復活
            if (!Schema::hasColumn('subscription_plans', 'duration_days')) {
                $table->integer('duration_days')->default(30)->after('price');
            }
        });
    }
};