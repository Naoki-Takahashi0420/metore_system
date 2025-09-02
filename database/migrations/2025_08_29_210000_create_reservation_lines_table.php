<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 予約ライン設定テーブル
        Schema::create('reservation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('line_name')->comment('ライン名（例：本ライン1、予備ライン1）');
            $table->enum('line_type', ['main', 'sub'])->comment('ライン種別（main:本ライン, sub:予備ライン）');
            $table->integer('line_number')->comment('ライン番号');
            $table->integer('capacity')->default(1)->comment('同時施術可能数');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            
            // 利用条件
            $table->boolean('allow_new_customers')->default(true)->comment('新規顧客許可');
            $table->boolean('allow_existing_customers')->default(true)->comment('既存顧客許可');
            $table->boolean('requires_staff')->default(false)->comment('スタッフ指定必須');
            $table->boolean('allows_simultaneous')->default(false)->comment('同時施術可能');
            
            // 機材管理
            $table->string('equipment_id')->nullable()->comment('使用機材ID');
            $table->string('equipment_name')->nullable()->comment('機材名');
            
            // 優先度とルール
            $table->integer('priority')->default(0)->comment('優先度（高い値が優先）');
            $table->json('availability_rules')->nullable()->comment('利用可能ルール（曜日・時間帯など）');
            
            $table->timestamps();
            
            $table->index(['store_id', 'line_type', 'is_active']);
            $table->unique(['store_id', 'line_name']);
        });
        
        // 予約ラインスケジュール（日別の利用可能時間）
        Schema::create('reservation_line_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_id')->constrained('reservation_lines')->onDelete('cascade');
            $table->date('date')->comment('日付');
            $table->time('start_time')->comment('開始時間');
            $table->time('end_time')->comment('終了時間');
            $table->boolean('is_available')->default(true)->comment('利用可能フラグ');
            $table->integer('capacity_override')->nullable()->comment('この日の容量上書き');
            $table->text('notes')->nullable()->comment('メモ');
            $table->timestamps();
            
            $table->unique(['line_id', 'date', 'start_time']);
            $table->index(['date', 'is_available']);
        });
        
        // 予約とラインの紐付け
        Schema::create('reservation_line_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->onDelete('cascade');
            $table->foreignId('line_id')->constrained('reservation_lines')->onDelete('cascade');
            $table->dateTime('start_datetime')->comment('開始日時');
            $table->dateTime('end_datetime')->comment('終了日時');
            $table->enum('assignment_type', ['auto', 'manual'])->default('auto')->comment('割当タイプ');
            $table->string('assignment_reason')->nullable()->comment('割当理由');
            $table->timestamps();
            
            $table->unique(['reservation_id']);
            $table->index(['line_id', 'start_datetime', 'end_datetime']);
        });
        
        // スタッフとラインの紐付け（小山・新宿店用）
        Schema::create('staff_line_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('line_id')->constrained('reservation_lines')->onDelete('cascade');
            $table->date('date')->comment('日付');
            $table->time('start_time')->comment('開始時間');
            $table->time('end_time')->comment('終了時間');
            $table->boolean('is_primary')->default(true)->comment('主担当フラグ');
            $table->timestamps();
            
            $table->unique(['staff_id', 'line_id', 'date', 'start_time']);
            $table->index(['date', 'staff_id']);
        });
        
        // 店舗の予約ライン設定を更新
        Schema::table('stores', function (Blueprint $table) {
            $table->integer('main_lines_count')->default(1)->after('max_concurrent_reservations')->comment('本ライン数');
            $table->integer('sub_lines_count')->default(0)->after('main_lines_count')->comment('予備ライン数');
            $table->boolean('use_staff_assignment')->default(false)->after('sub_lines_count')->comment('スタッフ指定制を使用');
            $table->boolean('use_equipment_management')->default(false)->after('use_staff_assignment')->comment('機材管理を使用');
            $table->json('line_allocation_rules')->nullable()->after('use_equipment_management')->comment('ライン割当ルール');
        });
    }

    public function down()
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'main_lines_count',
                'sub_lines_count',
                'use_staff_assignment',
                'use_equipment_management',
                'line_allocation_rules'
            ]);
        });
        
        Schema::dropIfExists('staff_line_assignments');
        Schema::dropIfExists('reservation_line_assignments');
        Schema::dropIfExists('reservation_line_schedules');
        Schema::dropIfExists('reservation_lines');
    }
};