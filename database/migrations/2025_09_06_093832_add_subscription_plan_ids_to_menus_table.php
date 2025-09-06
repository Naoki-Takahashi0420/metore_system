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
        Schema::table('menus', function (Blueprint $table) {
            // サブスク対象プランIDsを保存（JSON形式）
            $table->json('subscription_plan_ids')->nullable()->after('is_subscription_only')->comment('対象サブスクプランIDs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('subscription_plan_ids');
        });
    }
};