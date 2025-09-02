<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('display_order');
        });
    }

    public function down()
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->integer('display_order')->default(0);
        });
    }
};