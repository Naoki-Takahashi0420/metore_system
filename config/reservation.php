<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 予約確認通知設定
    |--------------------------------------------------------------------------
    */
    
    // フォールバック遅延時間（分）
    'fallback_delay_minutes' => env('RESERVATION_FALLBACK_DELAY_MINUTES', 5),
    
    // 静穏時間設定
    'quiet_hours_start' => env('RESERVATION_QUIET_HOURS_START', '21:00'),
    'quiet_hours_end' => env('RESERVATION_QUIET_HOURS_END', '08:00'),
    
    // LINE送信失敗時のリトライ回数（SMS切り替え前）
    'retry_before_fallback' => env('RESERVATION_RETRY_BEFORE_FALLBACK', 1),
    
    // SMS送信設定
    'sms' => [
        'enabled' => env('SMS_ENABLED', true),
        'service' => env('SMS_SERVICE', 'twilio'), // twilio, nexmo等
    ],
    
    // LINE Messaging API設定
    'line' => [
        'enabled' => env('LINE_ENABLED', true),
        'channel_access_token' => env('LINE_CHANNEL_ACCESS_TOKEN'),
        'channel_secret' => env('LINE_CHANNEL_SECRET'),
    ],
    
    // 確認通知メッセージテンプレート
    'messages' => [
        'line_confirmation' => "ご予約ありがとうございます。\n\n【予約詳細】\n店舗: {store_name}\n日時: {reservation_date} {start_time}\nメニュー: {menu_name}\n予約番号: {reservation_number}\n\nご不明な点がございましたら、お気軽にお問い合わせください。",
        
        'sms_confirmation' => "【{store_name}】ご予約確認\n{reservation_date} {start_time} {menu_name}\n予約番号: {reservation_number}\nお待ちしております。",
    ],
];