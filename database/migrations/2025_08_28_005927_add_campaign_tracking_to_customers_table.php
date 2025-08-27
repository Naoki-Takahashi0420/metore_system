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
            $table->timestamp('last_campaign_sent_at')->nullable()->comment('最後にキャンペーン配信した日時')->after('line_registered_at');
            $table->integer('campaign_send_count')->default(0)->comment('キャンペーン配信回数')->after('last_campaign_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['last_campaign_sent_at', 'campaign_send_count']);
        });
    }
};
