<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_settings', function (Blueprint $table) {
            $table->id();
            
            // 基本設定
            $table->boolean('send_confirmation')->default(true)->comment('予約確認送信');
            $table->boolean('send_reminder_24h')->default(true)->comment('24時間前リマインダー');
            $table->boolean('send_reminder_3h')->default(true)->comment('3時間前リマインダー');
            $table->boolean('send_follow_30d')->default(true)->comment('30日後フォロー');
            $table->boolean('send_follow_60d')->default(true)->comment('60日後フォロー');
            
            // メッセージテンプレート
            $table->text('message_confirmation')->nullable()->comment('予約確認メッセージ');
            $table->text('message_reminder_24h')->nullable()->comment('24時間前メッセージ');
            $table->text('message_reminder_3h')->nullable()->comment('3時間前メッセージ');
            $table->text('message_follow_30d')->nullable()->comment('30日後メッセージ');
            $table->text('message_follow_60d')->nullable()->comment('60日後メッセージ');
            
            $table->timestamps();
        });
        
        // デフォルトレコードを挿入
        DB::table('line_settings')->insert([
            'send_confirmation' => true,
            'send_reminder_24h' => true,
            'send_reminder_3h' => true,
            'send_follow_30d' => true,
            'send_follow_60d' => true,
            'message_confirmation' => "{{customer_name}}様\n\nご予約ありがとうございます！\n\n📅 {{reservation_date}} {{reservation_time}}\n📍 {{store_name}}\n💡 {{menu_name}}\n\n当日お会いできることを楽しみにしております。",
            'message_reminder_24h' => "{{customer_name}}様\n\n明日のご予約をお忘れなく！\n\n📅 {{reservation_date}} {{reservation_time}}\n📍 {{store_name}}\n\nお気をつけてお越しください。",
            'message_reminder_3h' => "{{customer_name}}様\n\n本日{{reservation_time}}にお待ちしております。\n\n📍 {{store_name}}\n\nもうすぐお会いできますね！",
            'message_follow_30d' => "{{customer_name}}様\n\n先日はありがとうございました。\nその後いかがお過ごしですか？\n\n次回のご予約で10%OFFいたします。\nぜひまたお越しください！",
            'message_follow_60d' => "{{customer_name}}様\n\nご無沙汰しております。\n\n特別に20%OFFクーポンをご用意しました。\nまたお会いできることを楽しみにしております！",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('line_settings');
    }
};