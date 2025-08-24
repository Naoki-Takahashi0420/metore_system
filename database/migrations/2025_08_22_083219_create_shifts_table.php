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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('スタッフID');
            $table->foreignId('store_id')->constrained()->onDelete('cascade')->comment('店舗ID');
            $table->date('shift_date')->comment('シフト日');
            $table->time('start_time')->comment('開始時刻');
            $table->time('end_time')->comment('終了時刻');
            $table->time('break_start')->nullable()->comment('休憩開始時刻');
            $table->time('break_end')->nullable()->comment('休憩終了時刻');
            $table->enum('status', ['scheduled', 'working', 'completed', 'cancelled'])->default('scheduled')->comment('ステータス');
            $table->text('notes')->nullable()->comment('備考');
            $table->boolean('is_available_for_reservation')->default(true)->comment('予約受付可能');
            $table->timestamps();
            
            // インデックス
            $table->index(['user_id', 'shift_date']);
            $table->index(['store_id', 'shift_date']);
            $table->index('shift_date');
            
            // 同じ日に同じスタッフが複数シフトを持たないようにユニーク制約
            $table->unique(['user_id', 'shift_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};