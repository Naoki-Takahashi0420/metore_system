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
        Schema::table('menus', function (Blueprint $table) {
            // カラム名を変更（default_contract_months → contract_months）
            // SQLiteの場合はカラム名変更がサポートされていないので、新しいカラムを追加してデータを移行
            if (Schema::hasColumn('menus', 'default_contract_months')) {
                // 新しいカラムを追加
                if (!Schema::hasColumn('menus', 'contract_months')) {
                    $table->integer('contract_months')->nullable()->after('is_subscription');
                }
            }
        });
        
        // データを移行
        if (Schema::hasColumn('menus', 'default_contract_months') && Schema::hasColumn('menus', 'contract_months')) {
            DB::table('menus')->update([
                'contract_months' => DB::raw('default_contract_months')
            ]);
        }
        
        // 古いカラムを削除
        Schema::table('menus', function (Blueprint $table) {
            if (Schema::hasColumn('menus', 'default_contract_months')) {
                $table->dropColumn('default_contract_months');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            if (!Schema::hasColumn('menus', 'default_contract_months')) {
                $table->integer('default_contract_months')->nullable()->after('is_subscription');
            }
        });
        
        // データを戻す
        if (Schema::hasColumn('menus', 'contract_months') && Schema::hasColumn('menus', 'default_contract_months')) {
            DB::table('menus')->update([
                'default_contract_months' => DB::raw('contract_months')
            ]);
        }
        
        Schema::table('menus', function (Blueprint $table) {
            if (Schema::hasColumn('menus', 'contract_months')) {
                $table->dropColumn('contract_months');
            }
        });
    }
};