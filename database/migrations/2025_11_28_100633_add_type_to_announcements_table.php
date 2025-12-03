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
        Schema::table('announcements', function (Blueprint $table) {
            // お知らせのタイプ: general（一般）, order_notification（発注通知）
            $table->string('type', 50)->default('general')->after('id');
            $table->index('type');
            
            // 発注通知用の関連情報（JSONで保存）
            $table->json('metadata')->nullable()->after('content');
        });
        
        // 既存のお知らせを「一般」タイプに更新
        DB::table('announcements')->update(['type' => 'general']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'metadata']);
        });
    }
};