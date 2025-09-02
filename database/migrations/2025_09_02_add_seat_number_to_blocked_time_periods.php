<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('blocked_time_periods', function (Blueprint $table) {
            $table->integer('seat_number')->nullable()->after('store_id')
                ->comment('特定席のブロック（null: 全席）');
        });
    }

    public function down()
    {
        Schema::table('blocked_time_periods', function (Blueprint $table) {
            $table->dropColumn('seat_number');
        });
    }
};