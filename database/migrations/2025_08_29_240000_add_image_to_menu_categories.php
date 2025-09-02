<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('description');
        });
    }

    public function down()
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};