<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_subscription_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained();
            $table->integer('amount');
            $table->string('payment_method')->default('credit_card');
            $table->string('status')->default('pending'); // pending, completed, failed, refunded
            $table->datetime('payment_date');
            $table->datetime('due_date');
            $table->string('transaction_id')->nullable();
            $table->json('payment_details')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['customer_id', 'status']);
            $table->index('payment_date');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};