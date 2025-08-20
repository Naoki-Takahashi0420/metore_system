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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reservation_number', 50)->unique()->comment('予約番号');
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('staff_id')->nullable()->constrained('users')->onDelete('set null');
            $table->date('reservation_date')->comment('予約日');
            $table->time('start_time')->comment('開始時刻');
            $table->time('end_time')->comment('終了時刻');
            $table->enum('status', [
                'pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'
            ])->default('pending')->comment('ステータス');
            $table->integer('guest_count')->default(1)->comment('来店人数');
            $table->decimal('total_amount', 10, 2)->default(0)->comment('合計金額');
            $table->decimal('deposit_amount', 10, 2)->default(0)->comment('預かり金');
            $table->string('payment_method', 50)->nullable()->comment('支払方法');
            $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid');
            $table->json('menu_items')->nullable()->comment('選択メニュー');
            $table->text('notes')->nullable()->comment('備考');
            $table->text('cancel_reason')->nullable()->comment('キャンセル理由');
            $table->timestamp('confirmed_at')->nullable()->comment('確定日時');
            $table->timestamp('cancelled_at')->nullable()->comment('キャンセル日時');
            $table->timestamps();
            
            // インデックス
            $table->index(['store_id', 'reservation_date']);
            $table->index(['store_id', 'status', 'reservation_date']);
            $table->index(['customer_id', 'status']);
            $table->index(['staff_id', 'reservation_date']);
            $table->unique(['staff_id', 'reservation_date', 'start_time'], 'unique_staff_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
