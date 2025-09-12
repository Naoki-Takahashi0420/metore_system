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

// 店舗別LINE処理
Schedule::command('line:send-reminders')
    ->everyMinute() // 毎分実行（各店舗の設定時刻をチェック）
    ->between('8:00', '21:00') // 8時〜21時の間のみ
    ->description('店舗別LINEリマインダー送信');

Schedule::command('line:send-followup')
    ->dailyAt('10:00') // 毎日午前10時に実行
    ->description('LINE 7日・15日フォローアップ送信');
