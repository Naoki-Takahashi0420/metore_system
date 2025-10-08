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
            // カラムが存在しない場合のみ追加
            if (!Schema::hasColumn('reservations', 'customer_ticket_id')) {
                $table->unsignedBigInteger('customer_ticket_id')->nullable()->after('customer_id');

                $table->foreign('customer_ticket_id')
                    ->references('id')
                    ->on('customer_tickets')
                    ->onDelete('set null');
            }

            if (!Schema::hasColumn('reservations', 'customer_subscription_id')) {
                $table->unsignedBigInteger('customer_subscription_id')->nullable()->after('customer_ticket_id');

                $table->foreign('customer_subscription_id')
                    ->references('id')
                    ->on('customer_subscriptions')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['customer_ticket_id']);
            $table->dropForeign(['customer_subscription_id']);
            $table->dropColumn(['customer_ticket_id', 'customer_subscription_id']);
        });
    }
};
