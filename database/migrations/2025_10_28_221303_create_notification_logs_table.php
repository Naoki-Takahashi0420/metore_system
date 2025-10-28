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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();

            // 関連エンティティ
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete()->comment('予約ID');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete()->comment('顧客ID');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->comment('管理者ID');
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete()->comment('店舗ID');

            // 通知情報
            $table->string('notification_type')->comment('通知種別（reservation_confirmation, reservation_change等）');
            $table->enum('channel', ['line', 'sms', 'email'])->comment('送信チャネル');
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending')->comment('送信ステータス');

            // 送信結果
            $table->string('message_id')->nullable()->comment('プロバイダ返却ID');
            $table->string('error_code')->nullable()->comment('エラーコード');
            $table->text('error_message')->nullable()->comment('エラーメッセージ');

            // PII保護（マスキング済み情報）
            $table->string('recipient_hash')->nullable()->comment('送信先のハッシュ値（電話番号/メール）');
            $table->string('recipient_masked')->nullable()->comment('送信先のマスク表示（080****1234）');

            // 重複防止
            $table->string('idempotency_key')->unique()->comment('重複防止キー（reservation_id + type + timestamp）');

            // 追加情報
            $table->json('metadata')->nullable()->comment('追加情報（JSON）');
            $table->timestamp('sent_at')->nullable()->comment('送信日時');

            $table->timestamps();

            // インデックス
            $table->index(['reservation_id', 'notification_type']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('idempotency_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
