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
        Schema::create('customer_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('ticket_plan_id')->nullable()->constrained('ticket_plans')->onDelete('set null')->comment('プランID（カスタム回数券の場合はNULL）');

            // 回数券情報（スナップショット）
            $table->string('plan_name')->comment('回数券名');
            $table->integer('total_count')->comment('総回数');
            $table->integer('used_count')->default(0)->comment('利用済み回数');
            // remaining_countは後でvirtual columnとして追加

            // 金額情報
            $table->integer('purchase_price')->comment('購入価格');

            // 有効期限
            $table->datetime('purchased_at')->comment('購入日時');
            $table->datetime('expires_at')->nullable()->comment('有効期限（NULL=無期限）');

            // ステータス
            $table->enum('status', ['active', 'expired', 'used_up', 'cancelled'])->default('active');

            // 決済情報
            $table->string('payment_method', 50)->nullable()->comment('支払い方法');
            $table->string('payment_reference')->nullable()->comment('決済参照ID');

            // 管理情報
            $table->text('notes')->nullable()->comment('備考');
            $table->foreignId('sold_by')->nullable()->constrained('users')->onDelete('set null')->comment('販売したスタッフID');
            $table->datetime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('cancel_reason')->nullable();

            $table->timestamps();

            // インデックス
            $table->index(['customer_id', 'status']);
            $table->index('expires_at');
            $table->index(['store_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_tickets');
    }
};
