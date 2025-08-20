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
        Schema::create('shift_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('staff_id')->constrained('users')->onDelete('cascade');
            $table->date('shift_date')->comment('シフト日');
            $table->time('start_time')->comment('開始時刻');
            $table->time('end_time')->comment('終了時刻');
            $table->time('break_start')->nullable()->comment('休憩開始');
            $table->time('break_end')->nullable()->comment('休憩終了');
            $table->enum('status', [
                'scheduled', 'confirmed', 'working', 'completed', 'cancelled'
            ])->default('scheduled')->comment('ステータス');
            $table->time('actual_start')->nullable()->comment('実際の開始時刻');
            $table->time('actual_end')->nullable()->comment('実際の終了時刻');
            $table->text('notes')->nullable()->comment('備考');
            $table->timestamps();
            
            // インデックス
            $table->index(['store_id', 'shift_date']);
            $table->index(['staff_id', 'shift_date']);
            $table->unique(['staff_id', 'shift_date', 'start_time'], 'unique_staff_shift');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_schedules');
    }
};
