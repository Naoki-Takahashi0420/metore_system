<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->enum('line_type', ['main', 'sub'])->default('main')->after('status');
            $table->integer('line_number')->default(1)->after('line_type');
            $table->index(['store_id', 'reservation_date', 'line_type']);
        });
    }

    public function down()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['line_type', 'line_number']);
        });
    }
};