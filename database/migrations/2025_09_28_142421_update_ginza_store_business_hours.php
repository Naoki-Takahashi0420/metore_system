<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Store;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 銀座店の営業時間を10:00-22:00に更新
        $store = Store::where('name', 'like', '%銀座%')->first();

        if ($store) {
            $businessHours = [];
            foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
                $businessHours[] = [
                    'day' => $day,
                    'open_time' => '10:00:00',
                    'close_time' => '22:00:00',
                    'is_closed' => false
                ];
            }

            $store->business_hours = $businessHours;
            $store->save();

            echo "Updated Ginza store business hours to 10:00-22:00\n";
        } else {
            echo "Ginza store not found\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 元の営業時間に戻す（10:00-21:00）
        $store = Store::where('name', 'like', '%銀座%')->first();

        if ($store) {
            $businessHours = [];
            foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
                $businessHours[] = [
                    'day' => $day,
                    'open_time' => '10:00:00',
                    'close_time' => '21:00:00',
                    'is_closed' => false
                ];
            }

            $store->business_hours = $businessHours;
            $store->save();
        }
    }
};
