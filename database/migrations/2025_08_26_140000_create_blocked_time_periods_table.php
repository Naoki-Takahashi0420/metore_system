<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('blocked_time_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->date('blocked_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('reason')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_pattern')->nullable(); // weekly, monthly
            $table->timestamps();
            
            $table->index(['store_id', 'blocked_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('blocked_time_periods');
    }
};