<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 全店舗のサブライン数を1に固定
        DB::table('stores')->update(['sub_lines_count' => 1]);
        
        // capacityも再計算（main_lines_count + 1）
        DB::statement('UPDATE stores SET capacity = main_lines_count + 1');
    }

    public function down()
    {
        // ロールバックは不要（すでに1が適切な値のため）
    }
};