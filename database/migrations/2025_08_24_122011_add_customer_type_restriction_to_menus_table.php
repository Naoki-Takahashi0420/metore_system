<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            // 顧客タイプ制限: all（全て）, new（新規のみ）, existing（既存のみ）
            $table->string('customer_type_restriction', 20)->default('all')->after('show_in_upsell');
            
            // カルテからのみ予約可能フラグ
            $table->boolean('medical_record_only')->default(false)->after('customer_type_restriction');
            
            // インデックス追加（検索高速化）
            $table->index(['customer_type_restriction', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropIndex(['customer_type_restriction', 'is_available']);
            $table->dropColumn(['customer_type_restriction', 'medical_record_only']);
        });
    }
};