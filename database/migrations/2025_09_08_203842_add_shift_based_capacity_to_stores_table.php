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
        Schema::table('stores', function (Blueprint $table) {
            // シフトベース時の実際の予約可能席数（設備制約）
            $table->integer('shift_based_capacity')->default(1)->after('main_lines_count');
            
            // 予約管理方式の切り替え日（NULL = 即座に適用）
            $table->date('mode_change_date')->nullable()->after('use_staff_assignment');
            
            // 将来の予約管理方式（切り替え予定がある場合）
            $table->boolean('future_use_staff_assignment')->nullable()->after('mode_change_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('shift_based_capacity');
            $table->dropColumn('mode_change_date');
            $table->dropColumn('future_use_staff_assignment');
        });
    }
};