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
        Schema::create('fc_invoice_item_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // テンプレート名
            $table->string('type')->default('custom'); // product, royalty, system_fee, custom
            $table->string('description');             // 項目名
            $table->decimal('unit_price', 10, 2);      // 単価
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('tax_rate', 5, 2)->default(10);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fc_invoice_item_templates');
    }
};
