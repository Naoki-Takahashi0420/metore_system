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
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('payment_source', ['spot', 'subscription', 'ticket', 'other'])
                ->default('spot')
                ->after('payment_method')
                ->comment('支払いソース（スポット/サブスク/回数券/その他）');

            $table->foreignId('customer_subscription_id')
                ->nullable()
                ->after('payment_source')
                ->constrained('customer_subscriptions')
                ->nullOnDelete()
                ->comment('利用したサブスクID');

            $table->foreignId('customer_ticket_id')
                ->nullable()
                ->after('customer_subscription_id')
                ->constrained('customer_tickets')
                ->nullOnDelete()
                ->comment('利用した回数券ID');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['customer_subscription_id']);
            $table->dropForeign(['customer_ticket_id']);
            $table->dropColumn(['payment_source', 'customer_subscription_id', 'customer_ticket_id']);
        });
    }
};
