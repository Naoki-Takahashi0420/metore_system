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
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->comment('電話番号');
            $table->string('otp_code', 6)->comment('OTPコード');
            $table->timestamp('expires_at')->comment('有効期限');
            $table->timestamp('verified_at')->nullable()->comment('認証完了日時');
            $table->integer('attempts')->default(0)->comment('試行回数');
            $table->timestamps();
            
            // インデックス
            $table->index(['phone', 'otp_code']);
            $table->index(['expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};
