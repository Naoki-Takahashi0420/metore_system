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
        // customer_subscriptionsテーブルに課金日を追加
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_subscriptions', 'billing_date')) {
                $table->date('billing_date')->nullable()->after('start_date')->comment('課金日');
            }
            if (!Schema::hasColumn('customer_subscriptions', 'contract_months')) {
                $table->integer('contract_months')->default(1)->after('billing_date')->comment('契約期間（月）');
            }
        });
        
        // subscription_plansテーブルは既にcontract_monthsがある
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('customer_subscriptions', 'billing_date')) {
                $table->dropColumn('billing_date');
            }
            if (Schema::hasColumn('customer_subscriptions', 'contract_months')) {
                $table->dropColumn('contract_months');
            }
        });
    }
};