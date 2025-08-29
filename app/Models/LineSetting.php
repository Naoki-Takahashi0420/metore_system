<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LineSetting extends Model
{
    protected $fillable = [
        'send_confirmation',
        'send_reminder_24h',
        'send_reminder_3h',
        'send_follow_30d',
        'send_follow_60d',
        'message_confirmation',
        'message_reminder_24h',
        'message_reminder_3h',
        'message_follow_30d',
        'message_follow_60d',
    ];

    protected $casts = [
        'send_confirmation' => 'boolean',
        'send_reminder_24h' => 'boolean',
        'send_reminder_3h' => 'boolean',
        'send_follow_30d' => 'boolean',
        'send_follow_60d' => 'boolean',
    ];

    /**
     * 設定を取得（シングルトン）
     */
    public static function getSettings(): self
    {
        return self::first() ?? self::create([
            'send_confirmation' => true,
            'send_reminder_24h' => true,
            'send_reminder_3h' => true,
            'send_follow_30d' => true,
            'send_follow_60d' => true,
        ]);
    }

    /**
     * メッセージに変数を適用
     */
    public function applyVariables(string $message, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $message = str_replace('{{' . $key . '}}', $value, $message);
        }
        return $message;
    }
}