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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name')->comment('氏名');
            $table->string('email')->unique()->comment('メールアドレス');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->comment('パスワード');
            $table->enum('role', ['superadmin', 'admin', 'manager', 'staff'])
                  ->default('staff')->comment('役職');
            $table->json('permissions')->nullable()->comment('権限設定');
            $table->json('specialties')->nullable()->comment('専門分野');
            $table->decimal('hourly_rate', 8, 2)->nullable()->comment('時給');
            $table->boolean('is_active')->default(true)->comment('アクティブ状態');
            $table->timestamp('last_login_at')->nullable()->comment('最終ログイン');
            $table->rememberToken();
            $table->timestamps();
            
            // インデックス
            $table->index(['store_id', 'role']);
            $table->index(['is_active']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
