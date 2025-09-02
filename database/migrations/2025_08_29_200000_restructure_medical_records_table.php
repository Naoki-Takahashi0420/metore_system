<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('medical_records', function (Blueprint $table) {
            // 接客メモ（内部用）
            $table->text('service_memo')->nullable()->after('notes')->comment('接客メモ（内部用）');
            $table->string('handled_by')->nullable()->after('service_memo')->comment('対応者');
            
            // 顧客管理情報
            $table->string('payment_method')->nullable()->after('handled_by')->comment('支払い方法');
            $table->string('reservation_source')->nullable()->after('payment_method')->comment('予約媒体');
            $table->text('visit_purpose')->nullable()->after('reservation_source')->comment('来店目的');
            $table->boolean('genetic_possibility')->nullable()->after('visit_purpose')->comment('遺伝の可能性');
            $table->boolean('has_astigmatism')->nullable()->after('genetic_possibility')->comment('乱視');
            $table->text('eye_diseases')->nullable()->after('has_astigmatism')->comment('目の病気（レーシック、白内障など）');
            $table->text('workplace_address')->nullable()->after('eye_diseases')->comment('職場や住所');
            $table->text('device_usage')->nullable()->after('workplace_address')->comment('スマホ・PC使用頻度');
            
            // 次回引き継ぎ
            $table->text('next_visit_notes')->nullable()->after('device_usage')->comment('次回引き継ぎメモ');
            
            // 施術記録（顧客に見せる）
            $table->integer('session_number')->nullable()->after('next_visit_notes')->comment('施術回数');
            $table->date('treatment_date')->nullable()->after('session_number')->comment('施術日');
            
            // 視力データ（複数回分を保存）
            $table->json('vision_records')->nullable()->after('treatment_date')->comment('視力記録データ');
            /* vision_records のJSON構造:
            [
                {
                    "session": 1,
                    "date": "2025-08-29",
                    "before_left": "0.3",
                    "before_right": "0.5",
                    "after_left": "0.7",
                    "after_right": "0.8",
                    "intensity": "強",
                    "duration": "60分",
                    "public_memo": "効果あり"
                }
            ]
            */
            
            $table->index('customer_id');
            $table->index('treatment_date');
        });
    }

    public function down()
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropColumn([
                'service_memo',
                'handled_by',
                'payment_method',
                'reservation_source',
                'visit_purpose',
                'genetic_possibility',
                'has_astigmatism',
                'eye_diseases',
                'workplace_address',
                'device_usage',
                'next_visit_notes',
                'session_number',
                'treatment_date',
                'vision_records'
            ]);
        });
    }
};