<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('shift_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('pattern_data');
            $table->boolean('is_default')->default(false);
            $table->integer('usage_count')->default(0);
            $table->timestamps();
            
            $table->index(['store_id', 'is_default']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('shift_patterns');
    }
};