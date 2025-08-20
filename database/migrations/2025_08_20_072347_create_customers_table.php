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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('last_name', 100)->comment('姓');
            $table->string('first_name', 100)->comment('名');
            $table->string('last_name_kana', 100)->nullable()->comment('姓カナ');
            $table->string('first_name_kana', 100)->nullable()->comment('名カナ');
            $table->string('phone', 20)->unique()->comment('電話番号');
            $table->string('email')->unique()->nullable()->comment('メールアドレス');
            $table->date('birth_date')->nullable()->comment('生年月日');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->comment('性別');
            $table->string('postal_code', 8)->nullable()->comment('郵便番号');
            $table->text('address')->nullable()->comment('住所');
            $table->json('preferences')->nullable()->comment('設定・嗜好');
            $table->json('medical_notes')->nullable()->comment('医療メモ');
            $table->boolean('is_blocked')->default(false)->comment('ブロック状態');
            $table->timestamp('last_visit_at')->nullable()->comment('最終来店日');
            $table->timestamp('phone_verified_at')->nullable()->comment('電話番号認証日時');
            $table->timestamps();
            
            // インデックス
            $table->index(['phone_verified_at']);
            $table->index(['last_visit_at']);
            $table->index(['is_blocked']);
            $table->index(['last_name', 'first_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
