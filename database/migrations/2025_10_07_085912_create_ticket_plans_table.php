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
        Schema::create('ticket_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');

            // プラン情報
            $table->string('name')->comment('回数券名（例: 5回券、10回券）');
            $table->integer('ticket_count')->comment('利用可能回数');
            $table->integer('price')->comment('販売価格');

            // 有効期限設定（どちらかまたは両方NULL=無期限）
            $table->integer('validity_days')->nullable()->comment('有効期限（日数）');
            $table->integer('validity_months')->nullable()->comment('有効期限（月数）');

            // 詳細情報
            $table->text('description')->nullable()->comment('説明');
            $table->boolean('is_active')->default(true)->comment('有効/無効');
            $table->integer('display_order')->default(0)->comment('表示順序');

            $table->timestamps();

            // インデックス
            $table->index(['store_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_plans');
    }
};
