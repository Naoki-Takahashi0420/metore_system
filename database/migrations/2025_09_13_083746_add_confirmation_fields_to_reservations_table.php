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
            // 既存のカラムを確認してから追加
            if (!Schema::hasColumn('reservations', 'confirmation_sent_at')) {
                $table->timestamp('confirmation_sent_at')->nullable()->comment('確認通知送信日時');
            }
            if (!Schema::hasColumn('reservations', 'confirmation_method')) {
                $table->string('confirmation_method', 20)->nullable()->comment('送信方法（line/sms）');
            }
            if (!Schema::hasColumn('reservations', 'line_confirmation_sent_at')) {
                $table->timestamp('line_confirmation_sent_at')->nullable()->comment('LINE送信日時');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['confirmation_sent_at', 'confirmation_method', 'line_confirmation_sent_at']);
        });
    }
};
