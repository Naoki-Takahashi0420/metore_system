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
        Schema::table('medical_records', function (Blueprint $table) {
            $table->date('actual_reservation_date')->nullable()->after('next_visit_date')->comment('実際の予約日');
            $table->integer('date_difference_days')->nullable()->after('actual_reservation_date')->comment('推奨日との差異（日数）');
            $table->enum('reservation_status', ['pending', 'booked', 'completed', 'cancelled'])->default('pending')->after('date_difference_days')->comment('予約ステータス');
            $table->timestamp('reminder_sent_at')->nullable()->after('reservation_status')->comment('リマインダー送信日時');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropColumn(['actual_reservation_date', 'date_difference_days', 'reservation_status', 'reminder_sent_at']);
        });
    }
};
