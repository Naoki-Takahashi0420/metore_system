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
        Schema::table('fc_orders', function (Blueprint $table) {
            $table->foreignId('fc_invoice_id')->nullable()->after('status')->constrained('fc_invoices')->nullOnDelete();
            $table->index('fc_invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fc_orders', function (Blueprint $table) {
            $table->dropForeign(['fc_invoice_id']);
            $table->dropIndex(['fc_invoice_id']);
            $table->dropColumn('fc_invoice_id');
        });
    }
};
