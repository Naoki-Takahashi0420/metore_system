<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_reminder_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('ルール名');
            $table->text('description')->nullable()->comment('説明');
            $table->json('target_labels')->comment('対象顧客ラベル');
            $table->json('trigger_conditions')->comment('実行条件');
            $table->json('reminder_schedule')->comment('リマインダースケジュール');
            $table->foreignId('message_template_id')->constrained('line_message_templates')->comment('使用メッセージテンプレート');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->integer('priority')->default(1)->comment('優先度（数値が小さいほど優先）');
            $table->integer('max_sends_per_customer')->default(1)->comment('1顧客あたり最大送信回数');
            $table->json('exclusion_conditions')->nullable()->comment('除外条件');
            $table->timestamps();
            
            $table->index(['is_active', 'priority']);
            $table->index('message_template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_reminder_rules');
    }
};