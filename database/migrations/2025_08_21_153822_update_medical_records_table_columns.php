<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            // 新しいカラムを追加
            $table->date('record_date')->nullable()->after('reservation_id')->comment('記録日');
            $table->text('chief_complaint')->nullable()->after('record_date')->comment('主訴・お悩み');
            $table->text('medical_history')->nullable()->after('medications')->comment('既往歴・医療履歴');
            $table->text('prescription')->nullable()->after('treatment')->comment('処方・指導');
            $table->foreignId('created_by')->nullable()->after('next_visit_date')->constrained('users')->comment('記録作成者');
        });
        
        // visit_dateをrecord_dateに移行
        if (Schema::hasColumn('medical_records', 'visit_date')) {
            // 既存のvisit_dateデータをrecord_dateにコピー
            DB::statement('UPDATE medical_records SET record_date = visit_date WHERE record_date IS NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropColumn(['record_date', 'chief_complaint', 'medical_history', 'prescription']);
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};