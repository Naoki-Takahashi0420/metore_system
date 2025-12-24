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
            $table->unsignedTinyInteger('billing_day')->nullable()->after('billing_start_date')
                ->comment('毎月の請求日（1-28）。nullの場合はbilling_start_dateの日を使用');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->dropColumn('billing_day');
        });
    }
};
