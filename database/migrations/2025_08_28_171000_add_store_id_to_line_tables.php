<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // LineReminderRulesに店舗IDを追加
        Schema::table('line_reminder_rules', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('id')
                ->constrained()->onDelete('cascade')
                ->comment('店舗別ルール（nullは全店舗共通）');
            
            $table->index(['store_id', 'is_active']);
        });

        // 店舗別LINE設定テーブル
        Schema::create('store_line_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('line_channel_id')->nullable()->comment('店舗別LINEチャンネルID');
            $table->string('line_channel_secret')->nullable()->comment('店舗別LINEチャンネルシークレット');
            $table->text('line_channel_token')->nullable()->comment('店舗別LINEチャンネルトークン');
            $table->string('line_add_friend_url')->nullable()->comment('友だち追加URL');
            $table->boolean('use_global_settings')->default(true)->comment('全体設定を使用するか');
            $table->json('reminder_settings')->nullable()->comment('店舗別リマインダー設定');
            $table->json('campaign_settings')->nullable()->comment('店舗別キャンペーン設定');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique('store_id');
        });

        // 顧客-店舗-LINE紐付けテーブル
        Schema::create('customer_store_line_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('line_user_id')->comment('LINE ユーザーID');
            $table->timestamp('connected_at')->comment('紐付け日時');
            $table->boolean('is_blocked')->default(false)->comment('ブロック状態');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->unique(['customer_id', 'store_id']);
            $table->index(['store_id', 'line_user_id']);
            $table->index(['customer_id', 'is_blocked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_store_line_connections');
        Schema::dropIfExists('store_line_settings');
        
        Schema::table('line_reminder_rules', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });
    }
};