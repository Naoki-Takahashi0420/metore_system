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
        Schema::table('fc_products', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('min_order_quantity');
            $table->index(['category_id', 'sort_order']);
        });
        
        // 既存データに連番を振る
        $products = DB::table('fc_products')
            ->orderBy('category_id')
            ->orderBy('id')
            ->get();
        
        $sortOrder = 0;
        $lastCategoryId = null;
        
        foreach ($products as $product) {
            if ($product->category_id !== $lastCategoryId) {
                $sortOrder = 0;
                $lastCategoryId = $product->category_id;
            }
            
            DB::table('fc_products')
                ->where('id', $product->id)
                ->update(['sort_order' => $sortOrder]);
            
            $sortOrder++;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fc_products', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};