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
            // 回数券での支払い情報
            $table->foreignId('customer_ticket_id')->nullable()->after('payment_status')->constrained('customer_tickets')->onDelete('set null')->comment('使用した回数券ID');
            $table->boolean('paid_with_ticket')->default(false)->after('customer_ticket_id')->comment('回数券で支払い済み');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['customer_ticket_id']);
            $table->dropColumn(['customer_ticket_id', 'paid_with_ticket']);
        });
    }
};
