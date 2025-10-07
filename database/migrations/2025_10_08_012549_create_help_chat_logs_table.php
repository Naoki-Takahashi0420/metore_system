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
        Schema::create('help_chat_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('page_name')->nullable(); // 閲覧中のページ
            $table->text('question'); // ユーザーの質問
            $table->text('answer')->nullable(); // Claudeの回答
            $table->boolean('is_resolved')->default(false); // 解決したか
            $table->text('feedback')->nullable(); // 未解決時のフィードバック
            $table->json('context')->nullable(); // ページコンテキスト情報
            $table->json('usage')->nullable(); // API使用量
            $table->timestamps();

            $table->index('user_id');
            $table->index('is_resolved');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('help_chat_logs');
    }
};
