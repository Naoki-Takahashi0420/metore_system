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
        Schema::create('fc_product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('headquarters_store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name'); // カテゴリ名
            $table->text('description')->nullable(); // 説明
            $table->integer('sort_order')->default(0); // 表示順
            $table->boolean('is_active')->default(true); // 有効フラグ
            $table->timestamps();

            $table->index(['headquarters_store_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fc_product_categories');
    }
};
