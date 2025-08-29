<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('price')->comment('月額料金');
            $table->integer('duration_days')->default(30)->comment('期間（日数）');
            $table->json('features')->nullable()->comment('特典内容');
            $table->integer('max_reservations')->nullable()->comment('月間予約可能数');
            $table->integer('discount_rate')->default(0)->comment('割引率（%）');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};