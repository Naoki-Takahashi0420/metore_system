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
        Schema::create('broadcast_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('subject', 100)->comment('件名/タイトル');
            $table->text('message')->comment('メッセージ本文');
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'failed'])
                  ->default('draft')->comment('ステータス');
            $table->timestamp('scheduled_at')->nullable()->comment('予約送信日時');
            $table->timestamp('sent_at')->nullable()->comment('送信完了日時');
            $table->unsignedInteger('total_recipients')->default(0)->comment('送信対象数');
            $table->unsignedInteger('line_count')->default(0)->comment('LINE送信数');
            $table->unsignedInteger('email_count')->default(0)->comment('メール送信数');
            $table->unsignedInteger('success_count')->default(0)->comment('成功数');
            $table->unsignedInteger('failed_count')->default(0)->comment('失敗数');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index('scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcast_messages');
    }
};
