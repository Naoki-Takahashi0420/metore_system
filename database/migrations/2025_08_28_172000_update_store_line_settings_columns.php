<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_line_settings', function (Blueprint $table) {
            // line_add_friend_url を line_official_account_id に変更
            $table->renameColumn('line_add_friend_url', 'line_official_account_id');
        });
        
        // コメントを更新
        Schema::table('store_line_settings', function (Blueprint $table) {
            $table->string('line_official_account_id')->nullable()
                ->comment('LINE公式アカウントID（@から始まる）')->change();
        });
    }

    public function down(): void
    {
        Schema::table('store_line_settings', function (Blueprint $table) {
            $table->renameColumn('line_official_account_id', 'line_add_friend_url');
        });
    }
};