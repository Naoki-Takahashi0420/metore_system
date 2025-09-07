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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('line_user_id')->nullable()->comment('LINE ユーザーID');
            $table->boolean('line_notifications_enabled')->default(true)->comment('LINE通知設定');
            $table->timestamp('line_linked_at')->nullable()->comment('LINE連携日時');
            $table->json('line_profile')->nullable()->comment('LINEプロフィール情報');
            
            $table->index(['line_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['line_user_id']);
            $table->dropColumn(['line_user_id', 'line_notifications_enabled', 'line_linked_at', 'line_profile']);
        });
    }
};
