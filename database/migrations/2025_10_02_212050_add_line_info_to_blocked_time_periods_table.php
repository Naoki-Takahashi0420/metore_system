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
        Schema::table('blocked_time_periods', function (Blueprint $table) {
            $table->string('line_type')->nullable()->after('reason'); // main, sub, staff, unassigned
            $table->integer('line_number')->nullable()->after('line_type');
            $table->unsignedBigInteger('staff_id')->nullable()->after('line_number');

            $table->foreign('staff_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blocked_time_periods', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
            $table->dropColumn(['line_type', 'line_number', 'staff_id']);
        });
    }
};
