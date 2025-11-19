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

// 回数券期限切れ通知（7日前）
Schedule::command('tickets:notify-expiring --days=7')
    ->dailyAt('09:00') // 毎日午前9時に実行
    ->description('回数券期限切れ7日前通知');

// 回数券期限切れ通知（3日前）
Schedule::command('tickets:notify-expiring --days=3')
    ->dailyAt('09:00') // 毎日午前9時に実行
    ->description('回数券期限切れ3日前通知');

// 回数券期限切れステータス更新
Schedule::command('tickets:update-expired')
    ->dailyAt('01:00') // 毎日午前1時に実行（深夜に実行）
    ->description('期限切れ回数券のステータス自動更新');

// 日次ヘルスチェック（ログファイル権限・基本動作確認）
Schedule::command('health:check')
    ->dailyAt('00:05') // 毎日午前0時5分に実行（日付変更直後）
    ->description('日次ヘルスチェック：ログ権限・DB・キュー確認');

// FC本部管理：支払期限リマインダー（3日前）
Schedule::command('fc:payment-reminder')
    ->dailyAt('09:00') // 毎日午前9時に実行
    ->description('FC請求書支払期限リマインダー送信');

// FC本部管理：支払期限超過チェック
Schedule::command('fc:check-overdue')
    ->dailyAt('10:00') // 毎日午前10時に実行
    ->description('FC請求書支払期限超過チェック・通知');
