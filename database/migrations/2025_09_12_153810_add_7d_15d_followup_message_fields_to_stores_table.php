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
            // 新しい7日・15日フォローアップメッセージフィールドを追加
            $table->text('line_followup_message_7days')->nullable()
                ->after('line_reminder_message')
                ->comment('7日後フォローアップメッセージ');
                
            $table->text('line_followup_message_15days')->nullable()
                ->after('line_followup_message_7days')
                ->comment('15日後フォローアップメッセージ');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'line_followup_message_7days',
                'line_followup_message_15days',
            ]);
        });
    }
};