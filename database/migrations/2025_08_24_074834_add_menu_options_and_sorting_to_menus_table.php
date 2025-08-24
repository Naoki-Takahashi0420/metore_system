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
            $table->integer('display_order')->default(0)->after('sort_order');
            $table->boolean('is_option')->default(false)->after('is_available');
            $table->boolean('show_in_upsell')->default(false)->after('is_option');
            $table->text('upsell_description')->nullable()->after('show_in_upsell');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn(['display_order', 'is_option', 'show_in_upsell', 'upsell_description']);
        });
    }
};
