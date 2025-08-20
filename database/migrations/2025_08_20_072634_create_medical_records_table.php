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
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('staff_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reservation_id')->nullable()->constrained()->onDelete('set null');
            $table->date('visit_date')->comment('来院日');
            $table->text('symptoms')->nullable()->comment('症状');
            $table->text('diagnosis')->nullable()->comment('診断');
            $table->text('treatment')->nullable()->comment('治療内容');
            $table->json('medications')->nullable()->comment('処方薬');
            $table->text('notes')->nullable()->comment('備考');
            $table->date('next_visit_date')->nullable()->comment('次回来院予定日');
            $table->timestamps();
            
            // インデックス
            $table->index(['customer_id', 'visit_date']);
            $table->index(['staff_id', 'visit_date']);
            $table->index(['reservation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
