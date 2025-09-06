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
            // サブスクリプション関連の新しいフィールド
            $table->integer('max_monthly_usage')->nullable()->after('default_contract_months');
            $table->boolean('auto_renewal')->default(true)->after('max_monthly_usage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('max_monthly_usage');
            $table->dropColumn('auto_renewal');
        });
    }
};