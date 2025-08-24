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
            $table->boolean('sms_notifications_enabled')->default(true)->after('is_blocked')
                ->comment('SMS通知を受け取るかどうか');
            $table->text('notification_preferences')->nullable()->after('sms_notifications_enabled')
                ->comment('通知設定（JSON形式）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['sms_notifications_enabled', 'notification_preferences']);
        });
    }
};