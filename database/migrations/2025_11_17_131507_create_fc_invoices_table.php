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
        Schema::create('fc_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique(); // 請求書番号（INV-YYYYMM-XXXX形式）
            $table->foreignId('fc_store_id')->constrained('stores')->cascadeOnDelete(); // 請求先FC店舗
            $table->foreignId('headquarters_store_id')->constrained('stores')->cascadeOnDelete(); // 請求元本部
            $table->string('status')->default('draft'); // draft, issued, sent, partial_paid, paid, overdue, cancelled
            $table->date('billing_period_start'); // 請求対象期間開始
            $table->date('billing_period_end'); // 請求対象期間終了
            $table->date('issue_date')->nullable(); // 発行日
            $table->date('due_date')->nullable(); // 支払期限
            $table->decimal('subtotal', 12, 2)->default(0); // 小計（税抜）
            $table->decimal('tax_amount', 12, 2)->default(0); // 消費税額
            $table->decimal('total_amount', 12, 2)->default(0); // 合計（税込）
            $table->decimal('paid_amount', 12, 2)->default(0); // 入金済み金額
            $table->decimal('outstanding_amount', 12, 2)->default(0); // 未払い金額
            $table->string('pdf_path')->nullable(); // PDF保存パス
            $table->text('notes')->nullable(); // 備考
            $table->timestamps();

            $table->index(['fc_store_id', 'status']);
            $table->index(['headquarters_store_id', 'status']);
            $table->index('invoice_number');
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fc_invoices');
    }
};
