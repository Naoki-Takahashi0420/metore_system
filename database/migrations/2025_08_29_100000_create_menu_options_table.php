<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('price')->default(0);
            $table->integer('duration_minutes')->default(0); // 追加時間
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_required')->default(false); // 必須オプション
            $table->integer('max_quantity')->default(1); // 最大選択数
            $table->timestamps();
            
            $table->index(['menu_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_options');
    }
};