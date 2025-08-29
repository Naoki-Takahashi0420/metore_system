<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->onDelete('cascade');
            $table->foreignId('menu_option_id')->constrained()->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->integer('price'); // 予約時点の価格
            $table->integer('duration_minutes'); // 予約時点の追加時間
            $table->timestamps();
            
            $table->index('reservation_id');
            $table->unique(['reservation_id', 'menu_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_options');
    }
};