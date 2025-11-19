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
        Schema::table('stores', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('line_reminder_days_before')->comment('銀行名');
            $table->string('bank_branch')->nullable()->after('bank_name')->comment('支店名');
            $table->string('bank_account_type')->nullable()->after('bank_branch')->comment('口座種別');
            $table->string('bank_account_number')->nullable()->after('bank_account_type')->comment('口座番号');
            $table->string('bank_account_name')->nullable()->after('bank_account_number')->comment('口座名義');
            $table->text('bank_transfer_note')->nullable()->after('bank_account_name')->comment('振込に関する注意事項');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'bank_name',
                'bank_branch',
                'bank_account_type',
                'bank_account_number',
                'bank_account_name',
                'bank_transfer_note',
            ]);
        });
    }
};
