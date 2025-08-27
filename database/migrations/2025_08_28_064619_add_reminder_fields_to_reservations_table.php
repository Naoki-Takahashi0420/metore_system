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
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->comment('リマインダー送信日時');
            $table->string('reminder_method')->nullable()->comment('送信方法 (line/sms/email)');
            $table->integer('reminder_count')->default(0)->comment('リマインダー送信回数');
            $table->timestamp('followup_sent_at')->nullable()->comment('フォローアップ送信日時');
            $table->timestamp('thank_you_sent_at')->nullable()->comment('お礼メッセージ送信日時');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'reminder_sent_at',
                'reminder_method', 
                'reminder_count',
                'followup_sent_at',
                'thank_you_sent_at'
            ]);
        });
    }
};
