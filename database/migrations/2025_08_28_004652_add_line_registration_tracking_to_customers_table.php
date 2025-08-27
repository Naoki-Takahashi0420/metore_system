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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('line_registration_source')->nullable()->after('line_notifications_enabled')
                ->comment('LINE登録の流入経路: reservation_complete, qr_direct, other');
            $table->unsignedBigInteger('line_registration_store_id')->nullable()->after('line_registration_source')
                ->comment('LINE登録時の店舗ID');
            $table->unsignedBigInteger('line_registration_reservation_id')->nullable()->after('line_registration_store_id')
                ->comment('LINE登録のきっかけとなった予約ID');
            $table->timestamp('line_registered_at')->nullable()->after('line_registration_reservation_id')
                ->comment('LINE登録日時');
            
            $table->foreign('line_registration_store_id')->references('id')->on('stores')->onDelete('set null');
            $table->foreign('line_registration_reservation_id')->references('id')->on('reservations')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['line_registration_store_id']);
            $table->dropForeign(['line_registration_reservation_id']);
            $table->dropColumn([
                'line_registration_source',
                'line_registration_store_id', 
                'line_registration_reservation_id',
                'line_registered_at'
            ]);
        });
    }
};
