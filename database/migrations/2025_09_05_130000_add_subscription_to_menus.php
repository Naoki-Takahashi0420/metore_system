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
        // メニューテーブルにサブスク関連カラムを追加
        Schema::table('menus', function (Blueprint $table) {
            $table->boolean('is_subscription')->default(false)->after('is_active')->comment('サブスク対応');
            $table->integer('subscription_monthly_price')->nullable()->after('is_subscription')->comment('月額料金');
            $table->integer('default_contract_months')->default(1)->after('subscription_monthly_price')->comment('デフォルト契約期間（月）');
        });
        
        // customer_subscriptionsテーブルにmenu_idを追加
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_subscriptions', 'menu_id')) {
                $table->foreignId('menu_id')->nullable()->after('store_id')->constrained()->comment('対象メニュー');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn(['is_subscription', 'subscription_monthly_price', 'default_contract_months']);
        });
        
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('customer_subscriptions', 'menu_id')) {
                $table->dropForeign(['menu_id']);
                $table->dropColumn('menu_id');
            }
        });
    }
};