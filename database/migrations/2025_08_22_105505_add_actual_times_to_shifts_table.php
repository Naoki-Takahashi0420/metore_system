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
        Schema::table('shifts', function (Blueprint $table) {
            $table->time('actual_start_time')->nullable()->after('is_available_for_reservation')->comment('実際の出勤時刻');
            $table->time('actual_break_start')->nullable()->after('actual_start_time')->comment('実際の休憩開始時刻');
            $table->time('actual_break_end')->nullable()->after('actual_break_start')->comment('実際の休憩終了時刻');
            $table->time('actual_end_time')->nullable()->after('actual_break_end')->comment('実際の退勤時刻');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn([
                'actual_start_time',
                'actual_break_start', 
                'actual_break_end',
                'actual_end_time'
            ]);
        });
    }
};