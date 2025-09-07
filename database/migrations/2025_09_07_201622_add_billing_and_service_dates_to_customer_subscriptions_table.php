<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            // 課金開始日と施術開始日を追加
            $table->date('billing_start_date')->nullable()->after('plan_id')->comment('課金開始日');
            $table->date('service_start_date')->nullable()->after('billing_start_date')->comment('施術利用開始日');
            
            // 既存のstart_dateをbilling_start_dateに移行するための準備
            // start_dateは後で削除予定
        });
        
        // 既存データの移行
        DB::table('customer_subscriptions')->whereNotNull('start_date')->update([
            'billing_start_date' => DB::raw('start_date'),
            'service_start_date' => DB::raw('start_date'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['billing_start_date', 'service_start_date']);
        });
    }
};