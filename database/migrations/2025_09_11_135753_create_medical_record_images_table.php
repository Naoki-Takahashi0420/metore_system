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
        Schema::create('medical_record_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained()->onDelete('cascade');
            $table->string('file_path')->comment('画像ファイルパス');
            $table->string('file_name')->comment('元のファイル名');
            $table->string('mime_type')->nullable()->comment('MIMEタイプ');
            $table->integer('file_size')->nullable()->comment('ファイルサイズ（バイト）');
            $table->string('title')->nullable()->comment('画像タイトル');
            $table->text('description')->nullable()->comment('画像の説明');
            $table->integer('display_order')->default(0)->comment('表示順序');
            $table->boolean('is_visible_to_customer')->default(true)->comment('顧客への表示可否');
            $table->enum('image_type', ['before', 'after', 'progress', 'reference', 'other'])
                ->default('other')->comment('画像タイプ（施術前/施術後/経過/参考/その他）');
            $table->timestamps();
            
            // インデックス
            $table->index(['medical_record_id', 'display_order']);
            $table->index('is_visible_to_customer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_record_images');
    }
};