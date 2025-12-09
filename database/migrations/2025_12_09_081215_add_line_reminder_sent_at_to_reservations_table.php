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
            // LINEリマインダー送信日時（既に存在する場合はスキップ）
            if (!Schema::hasColumn('reservations', 'line_reminder_sent_at')) {
                $table->timestamp('line_reminder_sent_at')->nullable()->after('updated_at');
            }
            // 一般リマインダー送信日時（既に存在する場合はスキップ）
            if (!Schema::hasColumn('reservations', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable()->after('line_reminder_sent_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'line_reminder_sent_at')) {
                $table->dropColumn('line_reminder_sent_at');
            }
            if (Schema::hasColumn('reservations', 'reminder_sent_at')) {
                $table->dropColumn('reminder_sent_at');
            }
        });
    }
};
