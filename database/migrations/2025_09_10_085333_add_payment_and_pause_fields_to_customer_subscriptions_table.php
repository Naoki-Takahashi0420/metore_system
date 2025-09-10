<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // カラムが既に存在するかチェック
        if (!Schema::hasColumn('customer_subscriptions', 'payment_failed')) {
            Schema::table('customer_subscriptions', function (Blueprint $table) {
                // 決済失敗管理
                $table->boolean('payment_failed')->default(false)->after('status');
                $table->timestamp('payment_failed_at')->nullable()->after('payment_failed');
                $table->string('payment_failed_reason')->nullable()->after('payment_failed_at');
                $table->text('payment_failed_notes')->nullable()->after('payment_failed_reason');
                
                // 休止管理
                $table->boolean('is_paused')->default(false)->after('payment_failed_notes');
                $table->date('pause_start_date')->nullable()->after('is_paused');
                $table->date('pause_end_date')->nullable()->after('pause_start_date');
                $table->unsignedBigInteger('paused_by')->nullable()->after('pause_end_date');
                
                // インデックス
                $table->index('payment_failed');
                $table->index('is_paused');
                $table->index('pause_end_date');
                
                // 外部キー
                $table->foreign('paused_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['paused_by']);
            $table->dropColumn([
                'payment_failed',
                'payment_failed_at',
                'payment_failed_reason',
                'payment_failed_notes',
                'is_paused',
                'pause_start_date',
                'pause_end_date',
                'paused_by'
            ]);
        });
    }
};