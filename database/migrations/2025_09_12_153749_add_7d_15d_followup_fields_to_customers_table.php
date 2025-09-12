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
            // 新しい7日・15日フォローアップトラッキングフィールドを追加
            $table->timestamp('line_followup_7d_sent_at')->nullable()
                ->after('line_reminder_sent_at')
                ->comment('7日フォローアップ送信日時');
                
            $table->timestamp('line_followup_15d_sent_at')->nullable()
                ->after('line_followup_7d_sent_at')
                ->comment('15日フォローアップ送信日時');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'line_followup_7d_sent_at',
                'line_followup_15d_sent_at',
            ]);
        });
    }
};