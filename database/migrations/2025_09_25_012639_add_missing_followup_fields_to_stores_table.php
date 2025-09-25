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
            // 30日と60日のフォローアップメッセージカラムを追加
            if (!Schema::hasColumn('stores', 'line_followup_message_30days')) {
                $table->text('line_followup_message_30days')->nullable()->after('line_followup_message_15days');
            }
            if (!Schema::hasColumn('stores', 'line_followup_message_60days')) {
                $table->text('line_followup_message_60days')->nullable()->after('line_followup_message_30days');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['line_followup_message_30days', 'line_followup_message_60days']);
        });
    }
};
