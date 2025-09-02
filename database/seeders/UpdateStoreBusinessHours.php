<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;

class UpdateStoreBusinessHours extends Seeder
{
    public function run()
    {
        // デフォルトの営業時間
        $defaultBusinessHours = [
            ['day' => 'monday', 'open_time' => '10:00', 'close_time' => '21:00', 'is_closed' => false],
            ['day' => 'tuesday', 'open_time' => '10:00', 'close_time' => '21:00', 'is_closed' => false],
            ['day' => 'wednesday', 'open_time' => '10:00', 'close_time' => '21:00', 'is_closed' => false],
            ['day' => 'thursday', 'open_time' => '10:00', 'close_time' => '21:00', 'is_closed' => false],
            ['day' => 'friday', 'open_time' => '10:00', 'close_time' => '21:00', 'is_closed' => false],
            ['day' => 'saturday', 'open_time' => '10:00', 'close_time' => '21:00', 'is_closed' => false],
            ['day' => 'sunday', 'open_time' => '10:00', 'close_time' => '21:00', 'is_closed' => false],
        ];

        // 全店舗に営業時間を設定
        Store::all()->each(function ($store) use ($defaultBusinessHours) {
            $store->update([
                'business_hours' => $defaultBusinessHours,
            ]);
            
            echo "Updated store: {$store->name}\n";
        });
    }
}