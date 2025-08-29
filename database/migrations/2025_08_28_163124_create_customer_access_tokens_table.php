<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('store_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('token', 64)->unique();
            $table->string('purpose')->default('existing_customer'); // existing_customer, vip, campaign等
            $table->datetime('expires_at')->nullable();
            $table->integer('usage_count')->default(0);
            $table->integer('max_usage')->nullable(); // null = 無制限
            $table->json('metadata')->nullable(); // 追加情報（割引率など）
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['token', 'is_active']);
            $table->index(['customer_id', 'is_active']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_access_tokens');
    }
};