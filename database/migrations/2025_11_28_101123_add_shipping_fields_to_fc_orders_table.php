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
        Schema::table('fc_orders', function (Blueprint $table) {
            // 発送管理フィールド
            $table->boolean('is_partial_shipped')->default(false); // 部分発送フラグ
            $table->text('shipping_notes')->nullable(); // 発送メモ
            $table->date('cutoff_date')->nullable(); // 締め日（15日/月末）
            $table->string('cutoff_cycle')->default('month_end'); // 締めサイクル: '15th', 'month_end'
            $table->json('shipping_history')->nullable(); // 発送履歴（JSON）
            $table->foreignId('shipped_by')->nullable()->constrained('users')->onDelete('set null'); // 発送処理者
            
            // インデックス
            $table->index(['status', 'cutoff_date']);
            $table->index(['fc_store_id', 'cutoff_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fc_orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'cutoff_date']);
            $table->dropIndex(['fc_store_id', 'cutoff_date']);
            $table->dropForeign(['shipped_by']);
            $table->dropColumn([
                'is_partial_shipped',
                'shipping_notes', 
                'cutoff_date',
                'cutoff_cycle',
                'shipping_history',
                'shipped_by'
            ]);
        });
    }
};
