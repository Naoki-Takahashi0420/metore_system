<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 予約テーブルにLINE送信追跡フィールドを追加
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('line_confirmation_sent_at')->nullable()
                ->after('line_reminder_sent_at')
                ->comment('LINE予約確認送信日時');
        });
        
        // 顧客テーブルにフォローアップ送信追跡フィールドを追加
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('line_followup_30d_sent_at')->nullable()
                ->after('line_registration_completed_at')
                ->comment('30日フォローアップ送信日時');
            $table->timestamp('line_followup_60d_sent_at')->nullable()
                ->after('line_followup_30d_sent_at')
                ->comment('60日フォローアップ送信日時');
        });
    }

    public function down()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('line_confirmation_sent_at');
        });
        
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'line_followup_30d_sent_at',
                'line_followup_60d_sent_at',
            ]);
        });
    }
};