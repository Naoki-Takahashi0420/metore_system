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
        Schema::create('presbyopia_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained()->onDelete('cascade');
            $table->string('status'); // 施術前/施術後

            // A(95%)
            $table->text('a_95_left')->nullable();
            $table->text('a_95_right')->nullable();

            // B(50%)
            $table->text('b_50_left')->nullable();
            $table->text('b_50_right')->nullable();

            // C(25%)
            $table->text('c_25_left')->nullable();
            $table->text('c_25_right')->nullable();

            // D(12%)
            $table->text('d_12_left')->nullable();
            $table->text('d_12_right')->nullable();

            // E(6%)
            $table->text('e_6_left')->nullable();
            $table->text('e_6_right')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presbyopia_measurements');
    }
};
