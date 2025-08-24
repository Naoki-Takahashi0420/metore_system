<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Shift;
use App\Models\User;
use App\Models\Store;
use Carbon\Carbon;

class ShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // スタッフユーザーを作成（存在しない場合）
        $staff1 = User::firstOrCreate(
            ['email' => 'staff1@example.com'],
            [
                'name' => '田中 太郎',
                'password' => bcrypt('password'),
                'role' => 'staff',
            ]
        );
        
        $staff2 = User::firstOrCreate(
            ['email' => 'staff2@example.com'],
            [
                'name' => '山田 花子',
                'password' => bcrypt('password'),
                'role' => 'staff',
            ]
        );
        
        $staff3 = User::firstOrCreate(
            ['email' => 'staff3@example.com'],
            [
                'name' => '佐藤 健',
                'password' => bcrypt('password'),
                'role' => 'staff',
            ]
        );
        
        $stores = Store::all();
        if ($stores->isEmpty()) {
            $this->command->info('店舗が見つかりません。先に店舗を作成してください。');
            return;
        }
        
        $mainStore = $stores->first();
        
        // 今週のシフトを作成
        $startOfWeek = Carbon::now()->startOfWeek();
        
        for ($i = 0; $i < 7; $i++) {
            $date = $startOfWeek->copy()->addDays($i);
            
            // 田中さんのシフト（朝番）
            if ($i < 5) { // 平日のみ
                Shift::create([
                    'user_id' => $staff1->id,
                    'store_id' => $mainStore->id,
                    'shift_date' => $date,
                    'start_time' => '09:00:00',
                    'end_time' => '18:00:00',
                    'break_start' => '12:00:00',
                    'break_end' => '13:00:00',
                    'status' => $date->isPast() ? 'completed' : ($date->isToday() ? 'working' : 'scheduled'),
                    'is_available_for_reservation' => true,
                    'notes' => null,
                ]);
            }
            
            // 山田さんのシフト（遅番）
            if ($i != 3) { // 木曜日以外
                Shift::create([
                    'user_id' => $staff2->id,
                    'store_id' => $mainStore->id,
                    'shift_date' => $date,
                    'start_time' => '13:00:00',
                    'end_time' => '21:00:00',
                    'break_start' => '17:00:00',
                    'break_end' => '18:00:00',
                    'status' => $date->isPast() ? 'completed' : 'scheduled',
                    'is_available_for_reservation' => true,
                    'notes' => null,
                ]);
            }
            
            // 佐藤さんのシフト（週末のみ）
            if ($i >= 5) { // 土日のみ
                Shift::create([
                    'user_id' => $staff3->id,
                    'store_id' => $mainStore->id,
                    'shift_date' => $date,
                    'start_time' => '10:00:00',
                    'end_time' => '19:00:00',
                    'break_start' => '14:00:00',
                    'break_end' => '15:00:00',
                    'status' => 'scheduled',
                    'is_available_for_reservation' => true,
                    'notes' => '週末担当',
                ]);
            }
        }
        
        // 来週のシフトも少し作成
        $nextWeek = Carbon::now()->addWeek()->startOfWeek();
        
        for ($i = 0; $i < 3; $i++) {
            $date = $nextWeek->copy()->addDays($i);
            
            Shift::create([
                'user_id' => $staff1->id,
                'store_id' => $mainStore->id,
                'shift_date' => $date,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'break_start' => '12:00:00',
                'break_end' => '13:00:00',
                'status' => 'scheduled',
                'is_available_for_reservation' => true,
                'notes' => null,
            ]);
        }
        
        $this->command->info('シフトデータを作成しました。');
    }
}