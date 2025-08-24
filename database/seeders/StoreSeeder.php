<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Store::create([
            'name' => '目のトレーニング 東京本店',
            'name_kana' => 'メノトレーニング トウキョウホンテン',
            'postal_code' => '106-0032',
            'prefecture' => '東京都',
            'city' => '港区',
            'address' => '六本木1-1-1',
            'phone' => '03-1234-5678',
            'email' => 'tokyo@eye-training.com',
            'opening_hours' => [
                'monday' => ['open' => '09:00', 'close' => '18:00'],
                'tuesday' => ['open' => '09:00', 'close' => '18:00'],
                'wednesday' => ['open' => '09:00', 'close' => '18:00'],
                'thursday' => ['open' => '09:00', 'close' => '18:00'],
                'friday' => ['open' => '09:00', 'close' => '18:00'],
                'saturday' => ['open' => '09:00', 'close' => '17:00'],
                'sunday' => null,
            ],
            'holidays' => ['日曜日', '祝日'],
            'capacity' => 10,
            'reservation_settings' => [
                'advance_booking_days' => 60,
                'min_interval_days' => 5,
                'max_concurrent_reservations' => 3,
                'cancellation_deadline_hours' => 24,
                'auto_confirm' => false,
            ],
            'is_active' => true,
        ]);

        Store::create([
            'name' => '目のトレーニング 大阪支店',
            'name_kana' => 'メノトレーニング オオサカシテン',
            'postal_code' => '542-0081',
            'prefecture' => '大阪府',
            'city' => '大阪市中央区',
            'address' => '南船場3-3-3',
            'phone' => '06-2345-6789',
            'email' => 'osaka@eye-training.com',
            'opening_hours' => [
                'monday' => ['open' => '10:00', 'close' => '19:00'],
                'tuesday' => ['open' => '10:00', 'close' => '19:00'],
                'wednesday' => ['open' => '10:00', 'close' => '19:00'],
                'thursday' => ['open' => '10:00', 'close' => '19:00'],
                'friday' => ['open' => '10:00', 'close' => '19:00'],
                'saturday' => ['open' => '10:00', 'close' => '18:00'],
                'sunday' => ['open' => '10:00', 'close' => '17:00'],
            ],
            'holidays' => ['年末年始'],
            'capacity' => 8,
            'reservation_settings' => [
                'advance_booking_days' => 30,
                'min_interval_days' => 7,
                'max_concurrent_reservations' => 2,
                'cancellation_deadline_hours' => 48,
                'auto_confirm' => true,
            ],
            'is_active' => true,
        ]);
    }
}