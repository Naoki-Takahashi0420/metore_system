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
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            // last_reset_at カラムを追加（既に存在しない場合）
            if (!Schema::hasColumn('customer_subscriptions', 'last_reset_at')) {
                $table->timestamp('last_reset_at')->nullable()->after('reset_day');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->dropColumn('last_reset_at');
        });
    }
};