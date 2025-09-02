<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // カテゴリーに時間と料金設定を追加
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->json('available_durations')->nullable()->after('sort_order')
                ->comment('提供可能な時間のリスト [30, 50, 80]');
            $table->json('duration_prices')->nullable()->after('available_durations')
                ->comment('時間別料金 {"30": 3000, "50": 5000, "80": 8000}');
        });
    }

    public function down()
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->dropColumn(['available_durations', 'duration_prices']);
        });
    }
};