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
            $table->string('company_postal_code')->nullable()->after('company_name');
            $table->string('company_address')->nullable()->after('company_postal_code');
            $table->string('company_phone')->nullable()->after('company_address');
            $table->string('company_contact_person')->nullable()->after('company_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['company_postal_code', 'company_address', 'company_phone', 'company_contact_person']);
        });
    }
};
