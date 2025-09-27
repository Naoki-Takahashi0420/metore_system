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
            // カルテからのみ予約可能フラグを再追加
            if (!Schema::hasColumn('menus', 'medical_record_only')) {
                $table->boolean('medical_record_only')->default(false)->after('customer_type_restriction');
                $table->index('medical_record_only');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            if (Schema::hasColumn('menus', 'medical_record_only')) {
                $table->dropIndex(['medical_record_only']);
                $table->dropColumn('medical_record_only');
            }
        });
    }
};