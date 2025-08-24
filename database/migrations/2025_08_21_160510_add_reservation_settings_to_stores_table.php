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
        Schema::table('stores', function (Blueprint $table) {
            $table->integer('reservation_slot_duration')->default(30)->after('settings')->comment('予約枠の単位（分）');
            $table->integer('max_advance_days')->default(30)->after('reservation_slot_duration')->comment('予約受付期間（日）');
            $table->integer('cancellation_deadline_hours')->default(24)->after('max_advance_days')->comment('キャンセル期限（時間）');
            $table->boolean('require_confirmation')->default(false)->after('cancellation_deadline_hours')->comment('予約確認が必要');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'reservation_slot_duration',
                'max_advance_days',
                'cancellation_deadline_hours',
                'require_confirmation'
            ]);
        });
    }
};