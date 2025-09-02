<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('stores', function (Blueprint $table) {
            // LINE設定（1店舗1LINE）
            $table->string('line_channel_access_token', 500)->nullable()->after('line_allocation_rules')->comment('LINE Channel Access Token');
            $table->string('line_channel_secret')->nullable()->after('line_channel_access_token')->comment('LINE Channel Secret');
            $table->string('line_official_account_id')->nullable()->after('line_channel_secret')->comment('LINE公式アカウントID（@で始まる）');
            $table->string('line_basic_id')->nullable()->after('line_official_account_id')->comment('LINE Basic ID');
            $table->string('line_qr_code_url')->nullable()->after('line_basic_id')->comment('友だち追加QRコードURL');
            $table->string('line_add_friend_url')->nullable()->after('line_qr_code_url')->comment('友だち追加URL');
            
            // LINE機能のON/OFF
            $table->boolean('line_enabled')->default(false)->after('line_add_friend_url')->comment('LINE連携有効');
            $table->boolean('line_send_reservation_confirmation')->default(true)->after('line_enabled')->comment('予約確認送信');
            $table->boolean('line_send_reminder')->default(true)->after('line_send_reservation_confirmation')->comment('リマインダー送信');
            $table->boolean('line_send_followup')->default(true)->after('line_send_reminder')->comment('フォローアップ送信');
            $table->boolean('line_send_promotion')->default(true)->after('line_send_followup')->comment('プロモーション送信可能');
            
            // メッセージテンプレート（店舗ごとにカスタマイズ可能）
            $table->text('line_reservation_message')->nullable()->after('line_send_promotion')->comment('予約確認メッセージ');
            $table->text('line_reminder_message')->nullable()->after('line_reservation_message')->comment('リマインダーメッセージ');
            $table->text('line_followup_message_30days')->nullable()->after('line_reminder_message')->comment('30日後フォローアップ');
            $table->text('line_followup_message_60days')->nullable()->after('line_followup_message_30days')->comment('60日後フォローアップ');
            
            // タイミング設定（店舗ごと）
            $table->time('line_reminder_time')->default('10:00')->after('line_followup_message_60days')->comment('リマインダー送信時刻');
            $table->integer('line_reminder_days_before')->default(1)->after('line_reminder_time')->comment('何日前にリマインダー送信');
        });
    }

    public function down()
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'line_channel_access_token',
                'line_channel_secret',
                'line_official_account_id',
                'line_basic_id',
                'line_qr_code_url',
                'line_add_friend_url',
                'line_enabled',
                'line_send_reservation_confirmation',
                'line_send_reminder',
                'line_send_followup',
                'line_send_promotion',
                'line_reservation_message',
                'line_reminder_message',
                'line_followup_message_30days',
                'line_followup_message_60days',
                'line_reminder_time',
                'line_reminder_days_before',
            ]);
        });
    }
};