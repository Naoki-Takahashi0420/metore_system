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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('店舗名');
            $table->string('name_kana')->nullable()->comment('店舗名カナ');
            $table->string('postal_code', 8)->nullable()->comment('郵便番号');
            $table->string('prefecture', 50)->nullable()->comment('都道府県');
            $table->string('city', 100)->nullable()->comment('市区町村');
            $table->string('address')->nullable()->comment('住所');
            $table->string('phone', 20)->unique()->comment('電話番号');
            $table->string('email')->unique()->nullable()->comment('メールアドレス');
            $table->json('opening_hours')->nullable()->comment('営業時間');
            $table->json('holidays')->nullable()->comment('定休日');
            $table->integer('capacity')->default(1)->comment('収容人数');
            $table->json('settings')->nullable()->comment('店舗設定');
            $table->json('reservation_settings')->nullable()->comment('予約設定');
            $table->boolean('is_active')->default(true)->comment('アクティブ状態');
            $table->timestamps();
            
            // インデックス
            $table->index(['is_active']);
            $table->index(['prefecture', 'city']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
