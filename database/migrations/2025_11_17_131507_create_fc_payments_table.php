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
        Schema::create('fc_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fc_invoice_id')->constrained('fc_invoices')->cascadeOnDelete();
            $table->decimal('amount', 12, 2); // 入金額
            $table->date('payment_date'); // 入金日
            $table->string('payment_method')->default('bank_transfer'); // bank_transfer, cash, other
            $table->string('reference_number')->nullable(); // 振込参照番号
            $table->text('notes')->nullable(); // 備考
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete(); // 確認者
            $table->timestamps();

            $table->index('fc_invoice_id');
            $table->index('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fc_payments');
    }
};
