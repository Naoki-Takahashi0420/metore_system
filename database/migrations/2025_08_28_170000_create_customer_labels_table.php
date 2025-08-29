<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('label_key', 50)->comment('ラベルキー');
            $table->string('label_name', 100)->comment('ラベル名');
            $table->timestamp('assigned_at')->comment('ラベル付与日時');
            $table->boolean('auto_assigned')->default(true)->comment('自動付与かどうか');
            $table->timestamp('expires_at')->nullable()->comment('ラベル有効期限');
            $table->json('metadata')->nullable()->comment('追加メタデータ');
            $table->text('reason')->nullable()->comment('付与理由');
            $table->timestamps();
            
            $table->index(['customer_id', 'label_key']);
            $table->index(['label_key', 'assigned_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_labels');
    }
};