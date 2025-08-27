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
        Schema::create('line_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('設定キー');
            $table->text('value')->nullable()->comment('設定値（JSON）');
            $table->string('name')->comment('設定名');
            $table->text('description')->nullable()->comment('設定説明');
            $table->string('type')->default('text')->comment('設定タイプ (text, boolean, select, textarea)');
            $table->json('options')->nullable()->comment('選択肢（select用）');
            $table->string('category')->default('general')->comment('カテゴリ');
            $table->integer('sort_order')->default(0)->comment('表示順序');
            $table->boolean('is_system')->default(false)->comment('システム設定フラグ');
            $table->timestamps();
            
            $table->index(['category', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('line_settings');
    }
};
