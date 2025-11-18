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
            // FC店舗タイプを追加（headquarters=本部, fc_store=加盟店, regular=通常店舗）
            $table->string('fc_type')->default('regular')->after('name');
            // 本部店舗ID（加盟店の場合、どの本部に所属するか）
            // SQLiteでは外部キー制約を後から追加できないため、単純なカラムとして追加
            $table->unsignedBigInteger('headquarters_store_id')->nullable()->after('fc_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['fc_type', 'headquarters_store_id']);
        });
    }
};
