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
            // 不足しているカラムを追加
            if (!Schema::hasColumn('stores', 'line_liff_id')) {
                $table->string('line_liff_id')->nullable()->after('line_bot_basic_id');
            }

            if (!Schema::hasColumn('stores', 'total_capacity')) {
                $table->integer('total_capacity')->nullable()->after('line_liff_id');
            }

            if (!Schema::hasColumn('stores', 'shift_info')) {
                $table->text('shift_info')->nullable()->after('total_capacity');
            }

            if (!Schema::hasColumn('stores', 'capacity_info')) {
                $table->text('capacity_info')->nullable()->after('shift_info');
            }

            if (!Schema::hasColumn('stores', 'staff_example')) {
                $table->text('staff_example')->nullable()->after('capacity_info');
            }

            if (!Schema::hasColumn('stores', 'line_setup_guide')) {
                $table->text('line_setup_guide')->nullable()->after('staff_example');
            }

            if (!Schema::hasColumn('stores', 'notification_flow_guide')) {
                $table->text('notification_flow_guide')->nullable()->after('line_setup_guide');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'line_liff_id',
                'total_capacity',
                'shift_info',
                'capacity_info',
                'staff_example',
                'line_setup_guide',
                'notification_flow_guide'
            ]);
        });
    }
};