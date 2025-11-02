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
        // customer_subscriptions テーブルに agreement_signed カラムを追加
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->boolean('agreement_signed')->default(true)->comment('同意書記入済み');
        });

        // customer_tickets テーブルに agreement_signed カラムを追加
        Schema::table('customer_tickets', function (Blueprint $table) {
            $table->boolean('agreement_signed')->default(true)->comment('同意書記入済み');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->dropColumn('agreement_signed');
        });

        Schema::table('customer_tickets', function (Blueprint $table) {
            $table->dropColumn('agreement_signed');
        });
    }
};
