<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 既存データを新カラムに移行
        DB::table('reservations')->where('is_sub', true)->update([
            'line_type' => 'sub',
            'line_number' => DB::raw('COALESCE(seat_number, 1)')
        ]);
        
        DB::table('reservations')->where('is_sub', false)->whereNull('line_type')->update([
            'line_type' => 'main',
            'line_number' => DB::raw('COALESCE(seat_number, 1)')
        ]);
        
        // 不要カラムを削除
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['is_sub', 'seat_number']);
        });
    }

    public function down()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->boolean('is_sub')->default(false)->after('status');
            $table->integer('seat_number')->nullable()->after('is_sub');
        });
        
        // データを復元
        DB::table('reservations')->where('line_type', 'sub')->update([
            'is_sub' => true,
            'seat_number' => DB::raw('line_number')
        ]);
        
        DB::table('reservations')->where('line_type', 'main')->update([
            'is_sub' => false,
            'seat_number' => DB::raw('line_number')
        ]);
    }
};