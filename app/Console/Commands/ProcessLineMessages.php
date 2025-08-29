<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SimpleLineService;
use App\Models\Reservation;
use App\Models\Customer;
use Carbon\Carbon;

class ProcessLineMessages extends Command
{
    protected $signature = 'line:process';
    protected $description = 'LINE自動メッセージ処理';

    public function handle()
    {
        $service = new SimpleLineService();
        $now = Carbon::now();
        
        // 24時間後の予約をチェック
        $tomorrow = $now->copy()->addDay();
        $reservations24h = Reservation::whereBetween('reservation_date', [
            $tomorrow->copy()->startOfHour(),
            $tomorrow->copy()->endOfHour()
        ])
        ->whereNull('reminder_sent_24h')
        ->get();
        
        foreach ($reservations24h as $reservation) {
            if ($service->sendReminder24h($reservation)) {
                $reservation->update(['reminder_sent_24h' => now()]);
                $this->info("24時間前リマインダー送信: 予約ID {$reservation->id}");
            }
        }
        
        // 3時間後の予約をチェック
        $later = $now->copy()->addHours(3);
        $reservations3h = Reservation::whereBetween('reservation_date', [
            $later->copy()->startOfHour(),
            $later->copy()->endOfHour()
        ])
        ->whereNull('reminder_sent_3h')
        ->get();
        
        foreach ($reservations3h as $reservation) {
            if ($service->sendReminder3h($reservation)) {
                $reservation->update(['reminder_sent_3h' => now()]);
                $this->info("3時間前リマインダー送信: 予約ID {$reservation->id}");
            }
        }
        
        // 30日前に初回来店した顧客
        $date30 = $now->copy()->subDays(30)->toDateString();
        $customers30d = Customer::whereHas('reservations', function($q) use ($date30) {
            $q->whereDate('reservation_date', $date30)
              ->where('is_first_visit', true);
        })
        ->whereDoesntHave('reservations', function($q) use ($date30) {
            $q->whereDate('reservation_date', '>', $date30);
        })
        ->whereNull('follow_sent_30d')
        ->get();
        
        foreach ($customers30d as $customer) {
            if ($service->sendFollow30d($customer)) {
                $customer->update(['follow_sent_30d' => now()]);
                $this->info("30日後フォロー送信: 顧客ID {$customer->id}");
            }
        }
        
        // 60日前に初回来店した顧客
        $date60 = $now->copy()->subDays(60)->toDateString();
        $customers60d = Customer::whereHas('reservations', function($q) use ($date60) {
            $q->whereDate('reservation_date', $date60)
              ->where('is_first_visit', true);
        })
        ->whereDoesntHave('reservations', function($q) use ($date60) {
            $q->whereDate('reservation_date', '>', $date60);
        })
        ->whereNull('follow_sent_60d')
        ->get();
        
        foreach ($customers60d as $customer) {
            if ($service->sendFollow60d($customer)) {
                $customer->update(['follow_sent_60d' => now()]);
                $this->info("60日後フォロー送信: 顧客ID {$customer->id}");
            }
        }
        
        $this->info('LINE処理完了');
    }
}