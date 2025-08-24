<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SMSリマインダーのスケジュール設定
Schedule::command('reminders:send --days=3')
    ->dailyAt('10:00') // 毎日午前10時に実行
    ->description('3日後の予約リマインダーSMS送信');

Schedule::command('reminders:send --days=1')
    ->dailyAt('17:00') // 毎日午後5時に実行  
    ->description('翌日の予約リマインダーSMS送信');
