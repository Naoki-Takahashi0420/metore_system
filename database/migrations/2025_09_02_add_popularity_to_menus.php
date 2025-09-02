<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->boolean('is_popular')->default(false)->after('show_in_upsell')
                ->comment('人気メニューフラグ');
            $table->integer('reservation_count')->default(0)->after('is_popular')
                ->comment('予約回数（人気度の判定用）');
        });
    }

    public function down()
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn(['is_popular', 'reservation_count']);
        });
    }
};