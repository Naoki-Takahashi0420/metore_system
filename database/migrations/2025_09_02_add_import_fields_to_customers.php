<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // 既存カラムの確認と追加
            if (!Schema::hasColumn('customers', 'customer_number')) {
                $table->string('customer_number', 20)->nullable()->after('id')->comment('顧客番号');
                $table->index('customer_number');
            }
            
            if (!Schema::hasColumn('customers', 'prefecture')) {
                $table->string('prefecture', 10)->nullable()->after('postal_code')->comment('都道府県');
            }
            
            if (!Schema::hasColumn('customers', 'city')) {
                $table->string('city', 100)->nullable()->after('prefecture')->comment('市区町村');
            }
            
            if (!Schema::hasColumn('customers', 'building')) {
                $table->string('building', 100)->nullable()->after('address')->comment('建物名');
            }
            
            if (!Schema::hasColumn('customers', 'notes')) {
                $table->text('notes')->nullable()->after('medical_notes')->comment('備考');
            }
            
            // birthdayエイリアス（birth_dateを使用）
            if (!Schema::hasColumn('customers', 'birthday') && Schema::hasColumn('customers', 'birth_date')) {
                // birth_dateを使うので、birthdayカラムは不要
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['customer_number', 'prefecture', 'city', 'building', 'notes']);
        });
    }
};