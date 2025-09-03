<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;

class CreateTestStoresSeeder extends Seeder
{
    public function run()
    {
        $stores = [
            ['name' => '渋谷店', 'code' => 'SBY', 'address' => '東京都渋谷区渋谷1-1-1'],
            ['name' => '新宿店', 'code' => 'SJK', 'address' => '東京都新宿区新宿2-2-2'],
            ['name' => '池袋店', 'code' => 'IKB', 'address' => '東京都豊島区池袋3-3-3'],
            ['name' => '品川店', 'code' => 'SNG', 'address' => '東京都港区品川4-4-4'],
            ['name' => '上野店', 'code' => 'UEN', 'address' => '東京都台東区上野5-5-5'],
            ['name' => '横浜店', 'code' => 'YKH', 'address' => '神奈川県横浜市中区6-6-6'],
            ['name' => '川崎店', 'code' => 'KWS', 'address' => '神奈川県川崎市川崎区7-7-7'],
            ['name' => '千葉店', 'code' => 'CHB', 'address' => '千葉県千葉市中央区8-8-8'],
            ['name' => '大宮店', 'code' => 'OMY', 'address' => '埼玉県さいたま市大宮区9-9-9'],
            ['name' => '立川店', 'code' => 'TCW', 'address' => '東京都立川市立川町10-10-10'],
        ];

        foreach ($stores as $index => $storeData) {
            Store::firstOrCreate(
                ['code' => $storeData['code']],
                [
                    'name' => $storeData['name'],
                    'address' => $storeData['address'],
                    'phone' => '03-' . rand(1000, 9999) . '-' . rand(1000, 9999),
                    'email' => strtolower($storeData['code']) . '@example.com',
                    'opening_hours' => json_encode([
                        'monday' => ['10:00', '20:00'],
                        'tuesday' => ['10:00', '20:00'],
                        'wednesday' => ['10:00', '20:00'],
                        'thursday' => ['10:00', '20:00'],
                        'friday' => ['10:00', '20:00'],
                        'saturday' => ['10:00', '18:00'],
                        'sunday' => ['10:00', '18:00'],
                    ]),
                    'business_hours' => json_encode([
                        'open' => '10:00',
                        'close' => '20:00'
                    ]),
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'capacity' => rand(20, 50),
                    'reservation_slot_duration' => 30,
                    'max_advance_days' => 30,
                    'cancellation_deadline_hours' => 24,
                    'require_confirmation' => false,
                ]
            );
        }

        echo "10店舗のテストデータを作成しました！\n";
    }
}