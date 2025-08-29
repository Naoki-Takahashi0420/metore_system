<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_line_settings', function (Blueprint $table) {
            // Channel IDは実は不要（LINE Messaging APIには使わない）
            $table->dropColumn('line_channel_id');
            
            // 友だち追加URLも不要（自動生成可能）
            if (Schema::hasColumn('store_line_settings', 'line_add_friend_url')) {
                $table->dropColumn('line_add_friend_url');
            }
            if (Schema::hasColumn('store_line_settings', 'line_official_account_id')) {
                $table->dropColumn('line_official_account_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('store_line_settings', function (Blueprint $table) {
            $table->string('line_channel_id')->nullable()->after('store_id');
            $table->string('line_add_friend_url')->nullable()->after('line_channel_token');
        });
    }
};