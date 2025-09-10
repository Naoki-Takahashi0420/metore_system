<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_pause_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_subscription_id')->constrained()->cascadeOnDelete();
            $table->date('pause_start_date');
            $table->date('pause_end_date');
            $table->foreignId('paused_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paused_at');
            $table->timestamp('resumed_at')->nullable();
            $table->string('resume_type')->nullable();
            $table->integer('cancelled_reservations_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // インデックス
            $table->index('customer_subscription_id');
            $table->index(['pause_start_date', 'pause_end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_pause_histories');
    }
};