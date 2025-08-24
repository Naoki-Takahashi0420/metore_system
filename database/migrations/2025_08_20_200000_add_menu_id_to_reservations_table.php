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
            $table->foreignId('menu_id')->nullable()->after('staff_id')->constrained()->onDelete('set null');
            
            // Add internal_notes and source columns while we're at it
            $table->text('internal_notes')->nullable()->after('notes')->comment('内部メモ');
            $table->string('source', 50)->default('website')->after('payment_status')->comment('予約経路');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['menu_id']);
            $table->dropColumn(['menu_id', 'internal_notes', 'source']);
        });
    }
};