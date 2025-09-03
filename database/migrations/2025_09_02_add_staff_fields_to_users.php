<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active_staff')->default(true)->after('role');
            $table->boolean('can_be_nominated')->default(true)->after('is_active_staff');
            $table->json('default_shift_hours')->nullable()->after('can_be_nominated');
            // デフォルト勤務時間の例：
            // {
            //   "monday": {"start": "10:00", "end": "20:00"},
            //   "tuesday": {"start": "10:00", "end": "20:00"},
            //   "wednesday": null, // 休み
            //   ...
            // }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active_staff', 'can_be_nominated', 'default_shift_hours']);
        });
    }
};