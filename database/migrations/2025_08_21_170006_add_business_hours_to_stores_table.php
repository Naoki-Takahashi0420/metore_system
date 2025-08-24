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
            $table->json('business_hours')->nullable()->after('holidays')->comment('営業時間設定');
        });
        
        // デフォルトの営業時間を設定
        $defaultBusinessHours = [
            ['day' => 'monday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
            ['day' => 'tuesday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
            ['day' => 'wednesday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
            ['day' => 'thursday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
            ['day' => 'friday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
            ['day' => 'saturday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
            ['day' => 'sunday', 'open_time' => null, 'close_time' => null, 'is_closed' => true],
        ];
        
        DB::table('stores')->update([
            'business_hours' => json_encode($defaultBusinessHours)
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('business_hours');
        });
    }
};