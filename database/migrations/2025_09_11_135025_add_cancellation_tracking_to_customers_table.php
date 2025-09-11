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
            $table->integer('cancellation_count')->default(0)->comment('キャンセル回数')->after('is_blocked');
            $table->integer('no_show_count')->default(0)->comment('来店なし回数')->after('cancellation_count');
            $table->integer('change_count')->default(0)->comment('予約変更回数')->after('no_show_count');
            $table->timestamp('last_cancelled_at')->nullable()->comment('最終キャンセル日時')->after('change_count');
            
            // インデックス追加
            $table->index('cancellation_count');
            $table->index('no_show_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'cancellation_count',
                'no_show_count', 
                'change_count',
                'last_cancelled_at'
            ]);
        });
    }
};