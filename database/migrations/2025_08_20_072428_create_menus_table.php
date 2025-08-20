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
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('category', 100)->nullable()->comment('カテゴリ');
            $table->string('name')->comment('メニュー名');
            $table->text('description')->nullable()->comment('説明');
            $table->decimal('price', 8, 2)->comment('価格');
            $table->integer('duration')->comment('所要時間（分）');
            $table->boolean('is_available')->default(true)->comment('提供可能');
            $table->integer('max_daily_quantity')->nullable()->comment('1日最大提供数');
            $table->integer('sort_order')->default(0)->comment('表示順');
            $table->json('options')->nullable()->comment('オプション設定');
            $table->json('tags')->nullable()->comment('タグ');
            $table->timestamps();
            
            // インデックス
            $table->index(['store_id', 'category']);
            $table->index(['store_id', 'is_available']);
            $table->index(['sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
