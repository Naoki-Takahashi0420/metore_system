<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 既存カラムの確認
        $columns = Schema::getColumnListing('menus');
        
        // 必要なカラムを追加（存在しない場合のみ）
        Schema::table('menus', function (Blueprint $table) use ($columns) {
            if (!in_array('category_id', $columns)) {
                $table->foreignId('category_id')->nullable()->after('store_id')->constrained('menu_categories')->onDelete('set null');
            }
            
            if (!in_array('duration_minutes', $columns)) {
                $table->integer('duration_minutes')->nullable()->after('name');
            }
            
            if (!in_array('is_visible_to_customer', $columns)) {
                $table->boolean('is_visible_to_customer')->default(true)->after('is_available');
            }
            
            if (!in_array('is_subscription_only', $columns)) {
                $table->boolean('is_subscription_only')->default(false)->after('is_visible_to_customer');
            }
            
            if (!in_array('requires_staff', $columns)) {
                $table->boolean('requires_staff')->default(false)->after('is_subscription_only');
            }
        });
        
        // インデックスを個別に追加（エラーを無視）
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS menus_category_id_is_available_index ON menus (category_id, is_available)');
        } catch (\Exception $e) {
            // インデックスが既に存在する場合は無視
        }
        
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS menus_duration_minutes_index ON menus (duration_minutes)');
        } catch (\Exception $e) {
            // インデックスが既に存在する場合は無視
        }
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            // 外部キー制約を削除（存在する場合）
            try {
                $table->dropForeign(['category_id']);
            } catch (\Exception $e) {
                // 外部キーが存在しない場合は無視
            }
            
            // カラムを削除（存在する場合）
            $columns = Schema::getColumnListing('menus');
            $columnsToRemove = [];
            
            if (in_array('duration_minutes', $columns)) {
                $columnsToRemove[] = 'duration_minutes';
            }
            if (in_array('is_visible_to_customer', $columns)) {
                $columnsToRemove[] = 'is_visible_to_customer';
            }
            if (in_array('is_subscription_only', $columns)) {
                $columnsToRemove[] = 'is_subscription_only';
            }
            if (in_array('requires_staff', $columns)) {
                $columnsToRemove[] = 'requires_staff';
            }
            
            if (!empty($columnsToRemove)) {
                $table->dropColumn($columnsToRemove);
            }
        });
    }
};