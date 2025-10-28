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
        Schema::table('notification_logs', function (Blueprint $table) {
            // マスキング関連カラムを削除
            $table->dropColumn(['recipient_hash', 'recipient_masked']);

            // 生の送信先情報を保存（電話番号、メール、LINE ID等）
            $table->string('recipient')->nullable()->after('error_message')->comment('送信先（生情報）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropColumn('recipient');

            // 元のカラムを復元
            $table->string('recipient_hash')->nullable()->after('error_message')->comment('送信先のハッシュ値（電話番号/メール）');
            $table->string('recipient_masked')->nullable()->after('recipient_hash')->comment('送信先のマスク表示（080****1234）');
        });
    }
};
