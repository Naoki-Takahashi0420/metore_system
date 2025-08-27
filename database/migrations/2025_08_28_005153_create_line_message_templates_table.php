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
        Schema::create('line_message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('メッセージキー (welcome, reminder, campaign等)');
            $table->string('name')->comment('管理用名称');
            $table->text('message')->comment('メッセージ内容');
            $table->json('variables')->nullable()->comment('使用可能変数一覧');
            $table->unsignedBigInteger('store_id')->nullable()->comment('店舗別設定の場合の店舗ID');
            $table->boolean('is_active')->default(true)->comment('有効/無効');
            $table->string('category')->default('general')->comment('カテゴリ (general, reminder, campaign, auto_reply)');
            $table->text('description')->nullable()->comment('管理者向け説明');
            $table->timestamps();
            
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->index(['category', 'store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('line_message_templates');
    }
};
