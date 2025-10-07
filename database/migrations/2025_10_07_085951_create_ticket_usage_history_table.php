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
        Schema::create('ticket_usage_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_ticket_id')->constrained('customer_tickets')->onDelete('cascade');
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->onDelete('set null')->comment('予約ID');

            // 利用情報
            $table->datetime('used_at')->comment('利用日時');
            $table->integer('used_count')->default(1)->comment('利用回数（通常は1）');

            // 取り消し情報
            $table->boolean('is_cancelled')->default(false);
            $table->datetime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('cancel_reason')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            // インデックス
            $table->index('customer_ticket_id');
            $table->index('reservation_id');
            $table->index(['customer_ticket_id', 'is_cancelled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_usage_history');
    }
};
